<?php

/**
 * A modern, secure, and maintainable HTML sanitizer for PHP.
 *
 * Incorporates advanced features including srcset/data-* handling,
 * DoS protection for attribute counts, custom validators, and more robust
 * configuration options. It remains built on DOMDocument for security.
 */
class HtmlSanitizer
{
    protected array $config;
    protected DOMDocument $dom;

    public function __construct(array $config = [])
    {
        $this->dom = new DOMDocument();
        $this->processConfig($config);
    }

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        libxml_use_internal_errors(true);
        $this->dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $root = $this->dom->firstChild;
        if ($root) {
            $this->walkNode($root);
        }

        $cleanHtml = '';
        if ($root && $root->childNodes) {
            foreach ($root->childNodes as $node) {
                $cleanHtml .= $this->dom->saveHTML($node);
            }
        }
        
        return $cleanHtml;
    }

    protected function walkNode(DOMNode $node, int $depth = 0): void
    {
        if ($depth > $this->config['max_depth']) {
            $node->parentNode->removeChild($node);
            return;
        }

        if ($node instanceof DOMElement) {
            if ($node->hasChildNodes()) {
                for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
                    $this->walkNode($node->childNodes->item($i), $depth + 1);
                }
            }

            $tagName = strtolower($node->tagName);

            if (!isset($this->config['elements'][$tagName])) {
                $this->unwrapNode($node);
                return;
            }

            if ($node->hasAttributes()) {
                // DoS Protection: Attribute count
                if ($node->attributes->length > $this->config['max_attributes']) {
                    // Remove all attributes if the count is excessive
                    for ($i = $node->attributes->length - 1; $i >= 0; $i--) {
                        $node->removeAttribute($node->attributes->item($i)->name);
                    }
                } else {
                    for ($i = $node->attributes->length - 1; $i >= 0; $i--) {
                        $attribute = $node->attributes->item($i);
                        $this->sanitizeAttribute($node, $attribute);
                    }
                }
            }
        } elseif (!$node instanceof DOMText) {
            $node->parentNode->removeChild($node);
        }
    }

    protected function sanitizeAttribute(DOMElement $node, DOMAttr $attribute): void
    {
        $attrName = strtolower($attribute->name);
        $attrValue = $attribute->value;
        $tagName = strtolower($node->tagName);

        if (strpos($attrName, 'on') === 0) {
            $node->removeAttribute($attrName);
            return;
        }

        $allowedAttributes = $this->config['attributes'][$tagName] ?? [];
        $globalAttributes = $this->config['attributes']['*'] ?? [];
        $isDataAttribute = strpos($attrName, 'data-') === 0;

        $isAllowed = in_array($attrName, $allowedAttributes)
                  || in_array($attrName, $globalAttributes)
                  || ($isDataAttribute && in_array('data-*', $globalAttributes));

        if (!$isAllowed) {
            $node->removeAttribute($attrName);
            return;
        }

        // Apply custom validator if one exists
        $validator = $this->config['validators'][$tagName][$attrName] ?? null;
        if ($validator && is_callable($validator)) {
            $validatedValue = $validator($attrValue);
            if ($validatedValue === null) {
                $node->removeAttribute($attrName);
            } else {
                $node->setAttribute($attrName, $validatedValue);
            }
            return;
        }

        // Default sanitization logic
        if (in_array($attrName, ['href', 'src', 'action'])) {
            $url = $this->validateUrl($attrValue);
            if ($url === null) $node->removeAttribute($attrName);
            else $node->setAttribute($attrName, $url);
        } elseif ($attrName === 'srcset') {
            $node->setAttribute($attrName, $this->sanitizeSrcset($attrValue));
        } elseif ($attrName === 'style') {
            $style = $this->sanitizeStyle($attrValue);
            if (empty($style)) $node->removeAttribute($attrName);
            else $node->setAttribute($attrName, $style);
        } else {
            $node->setAttribute($attrName, $this->sanitizeGenericAttribute($attrValue));
        }
    }

    protected function validateUrl(string $url): ?string
    {
        $url = trim($url);
        if (strpos($url, '//') === 0) {
            return $this->config['allow_protocol_relative'] ? $url : null;
        }

        if (strpos($url, ':') === false) return $url;

        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME));
        if ($scheme === null || !in_array($scheme, $this->config['schemes'])) return null;

        return $url;
    }

    protected function sanitizeSrcset(string $srcset): string
    {
        $validParts = [];
        $parts = explode(',', $srcset);
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;
            
            $urlPart = preg_split('/\s+/', $part, 2);
            $url = $this->validateUrl($urlPart[0]);
            
            if ($url !== null) {
                $descriptor = $urlPart[1] ?? '';
                // Basic validation for descriptor to ensure it's not malicious
                if (preg_match('/^[\d.]+[wx]$/', $descriptor) || empty($descriptor)) {
                    $validParts[] = $url . (empty($descriptor) ? '' : ' ' . $descriptor);
                }
            }
        }
        return implode(', ', $validParts);
    }
    
    protected function sanitizeStyle(string $css): string
    {
        $cleanDeclarations = [];
        $declarations = explode(';', $css);

        foreach ($declarations as $declaration) {
            if (strpos($declaration, ':') === false) continue;
            
            [$property, $value] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            $value = trim($value);

            if (!isset($this->config['css_properties'][$property])) continue;
            
            if (preg_match('/url\s*\(/i', $value)) {
                // Use validateUrl to check schemes inside url()
                preg_match('/url\s*\(\s*["\']?([^)]+)["\']?\s*\)/i', $value, $matches);
                if (!isset($matches[1]) || $this->validateUrl($matches[1]) === null) {
                    continue;
                }
            }
            
            if (preg_match('/(expression|javascript|behavior)/i', $value)) continue;

            $cleanDeclarations[] = "{$property}: {$value}";
        }

        return implode('; ', $cleanDeclarations);
    }

    protected function sanitizeGenericAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function unwrapNode(DOMNode $node): void
    {
        if ($node->hasChildNodes()) {
            while ($node->firstChild) {
                $child = $node->removeChild($node->firstChild);
                $node->parentNode->insertBefore($child, $node);
            }
        }
        $node->parentNode->removeChild($node);
    }

    protected function processConfig(array $userConfig): void
    {
        $defaultConfig = [
            'max_depth' => 100,
            'max_attributes' => 50,
            'allow_protocol_relative' => false,
            'elements' => [
                'a', 'b', 'strong', 'i', 'em', 'u', 'p', 'br', 'div', 'span', 'ul', 'ol', 'li',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code', 'img',
                'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
            ],
            'attributes' => [
                'a' => ['href', 'title'],
                'img' => ['src', 'srcset', 'alt', 'title', 'width', 'height'],
                '*' => ['class', 'id', 'title', 'data-*'],
            ],
            'schemes' => ['http', 'https', 'mailto'],
            'css_properties' => [
                'color', 'background-color', 'font-size', 'font-weight',
                'text-align', 'text-decoration', 'width', 'height',
                'margin', 'padding', 'border', 'border-collapse',
            ],
            'validators' => [],
        ];

        $this->config = array_replace_recursive($defaultConfig, $userConfig);
        
        $this->config['elements'] = array_fill_keys($this->config['elements'], 1);
        $this->config['css_properties'] = array_fill_keys($this->config['css_properties'], 1);
    }
}