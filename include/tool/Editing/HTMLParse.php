<?php
declare(strict_types=1);
namespace gp\tool\Editing;
defined('is_running') or die('Not an entry point...');

/**
 * A custom, non-validating HTML parser that converts an HTML string into an array structure.
 * It's designed to be fast and handle real-world, often imperfect, HTML.
 */
class HTMLParse
{
    public string $doc = '';
    public array $dom_array = [];
    public array $errors = [];

    private int $doc_length;
    private int $position = 0;

    private string $mark_double_slash;
    private string $mark_escaped_single;
    private string $mark_escaped_double;

    public function __construct(string $text)
    {
        $this->doc = $text;
        $this->doc_length = strlen($text);
        $this->Init_Parse();
        $this->Parse();
    }

    public function Init_Parse(): void
    {
        $this->generateMarkers();
    }

    private function generateMarkers(): void
    {
        $this->mark_double_slash = $this->uniqueMarker();
        $this->mark_escaped_single = $this->uniqueMarker();
        $this->mark_escaped_double = $this->uniqueMarker();
    }

    private function uniqueMarker(): string
    {
        static $counter = 0;
        return "\x01".hash('xxh3', microtime().$counter++)."\x02";
    }

    private function addError(string $message): void
    {
        $this->errors[] = "Error at position {$this->position}: {$message}";
    }

    public function Parse(): void
    {
        while ($this->position < $this->doc_length) {
            $char = $this->doc[$this->position];

            if ($char !== '<') {
                $this->parseTextContent();
                continue;
            }

            if ($this->handleCommentIfAny()) {
                continue;
            }

            $tag_info = $this->parseTag();
            if ($tag_info === null) {
                // If parseTag fails, treat the '<' as literal text
                $this->dom_array[] = '&lt;';
                $this->position++;
                continue;
            }

            // If it's an opening tag for a special content element...
            if ($tag_info['name'][0] !== '/' && !$tag_info['self_closing']) {
                 $this->handleSpecialContent($tag_info['name']);
            }
        }
    }

    private function parseTextContent(): void
    {
        $next_tag_pos = strpos($this->doc, '<', $this->position);
        if ($next_tag_pos === false) {
            $text = substr($this->doc, $this->position);
            $this->position = $this->doc_length;
        } else {
            $text = substr($this->doc, $this->position, $next_tag_pos - $this->position);
            $this->position = $next_tag_pos;
        }

        if ($text !== '') {
            $this->dom_array[] = $text;
        }
    }

    private function handleCommentIfAny(): bool
    {
        if (substr_compare($this->doc, '<!--', $this->position, 4) === 0) {
            $end_pos = strpos($this->doc, '-->', $this->position + 4);
            if ($end_pos === false) {
                $content = substr($this->doc, $this->position + 4);
                $this->position = $this->doc_length;
                $this->addError("Unclosed HTML comment.");
            } else {
                $content = substr($this->doc, $this->position + 4, $end_pos - ($this->position + 4));
                $this->position = $end_pos + 3;
            }
            $this->dom_array[] = ['comment' => $content];
            return true;
        }
        return false;
    }

    /** @return ?array{name: string, self_closing: bool} */
    private function parseTag(): ?array
    {
        $original_tag_start_pos = $this->position;
        $this->position++; // Skip '<'

        if ($this->position >= $this->doc_length) {
            $this->position = $original_tag_start_pos; // backtrack
            return null;
        }

        $is_closing_tag_char = ($this->doc[$this->position] === '/');
        if ($is_closing_tag_char) {
            $this->position++; // Skip '/' for tag name parsing
        }

        $tag_name = $this->parseTagName();

        if ($tag_name === null || $tag_name === '') {
            $this->position = $original_tag_start_pos; // backtrack
            return null;
        }

        $element = ['tag' => $tag_name];
        $self_closing = false;

        if ($is_closing_tag_char) {
            $element['tag'] = '/' . $tag_name;
        } else { // Only parse attributes for opening tags
            $element['attributes'] = $this->parseAttributes();
        }

        // Find the end of the tag
        $gt_pos = strpos($this->doc, '>', $this->position);
        if ($gt_pos === false) {
            $this->addError("Unclosed tag '{$element['tag']}'.");
            $this->position = $this->doc_length; // Consume rest of document
            $element['self_closing'] = false;
            $this->dom_array[] = $element;
            return ['name' => $element['tag'], 'self_closing' => false];
        }

        // Check for XML-style self-closing tags like <br />
        if (!$is_closing_tag_char) {
            $before_gt_segment = substr($this->doc, $this->position, $gt_pos - $this->position);
            $trimmed_before_gt = rtrim($before_gt_segment);

            // CHANGE 3: Use substr() for a cleaner, more modern check.
            if (substr($trimmed_before_gt, -1) === '/') {
                $self_closing = true;
            }
        }
        
        $element['self_closing'] = $self_closing;
        $this->dom_array[] = $element;
        $this->position = $gt_pos + 1;

        return ['name' => $element['tag'], 'self_closing' => $self_closing];
    }

    private function parseTagName(): ?string
    {
        $name_len = strspn(
            $this->doc,
            'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_:.-',
            $this->position
        );

        if ($name_len === 0) {
            return null;
        }

        $name = substr($this->doc, $this->position, $name_len);
        $this->position += $name_len;        
       
        return strtolower($name);
    }

    /** @return array<string, string|null> */
    private function parseAttributes(): array
    {
        $attributes = [];
        // This regex finds attributes one by one, from the current position.
        $pattern = '/
            \G             # Anchor to the current position in the string
            \s+            # Require at least one space before an attribute
            (?!/?>)        # Negative lookahead: ensure we are not at the end of the tag (/> or >)
            ([^\s=<>\/]+)  # Capture group 1: The attribute name
            (?:            # Optional group for the value part
                \s*=\s*    # The equals sign, with optional whitespace
                (?:
                    "([^"]*)"  # Capture group 2: Double-quoted value
                    |          # OR
                    \'([^\']*)\'  # Capture group 3: Single-quoted value
                    |          # OR
                    ([^\s"\'=<>`]+) # Capture group 4: Unquoted value
                )
            )?             # The entire value part is optional (for boolean attributes)
        /ix'; // Case-insensitive and extended mode

        while (preg_match($pattern, $this->doc, $matches, PREG_OFFSET_CAPTURE, $this->position)) {
            $name = strtolower($matches[1][0]);

            $value = $matches[2][0] ?? $matches[3][0] ?? $matches[4][0] ?? null;

            if (!isset($attributes[$name])) {
                 $attributes[$name] = $value !== null ?
                    htmlspecialchars_decode($value, ENT_QUOTES) :
                    null; // Store null for boolean attributes like 'disabled'
            }

            $this->position = $matches[0][1] + strlen($matches[0][0]);
        }
        return $attributes;
    }

    private function handleSpecialContent(string $tag_name_from_parser): void
    {        
        if (!in_array($tag_name_from_parser, ['script', 'style'])) {
            return;
        }

        $content_start_pos = $this->position;
        // Use the already-lowercased tag name
        $end_tag_to_find = "</{$tag_name_from_parser}>";

        $remaining_doc_part = substr($this->doc, $content_start_pos);
        if ($remaining_doc_part === false || $remaining_doc_part === '') {
            $this->addError("Unclosed special tag '<{$tag_name_from_parser}>'.");
            return;
        }

        // IMPORTANT: This logic correctly handles cases like `var x = "</script>";` inside a script tag.
        // It temporarily escapes certain sequences to prevent a premature match.
        $escaped_remaining_part = str_replace(
            ['\\\\', '\\\'', '\\"', '</'],
            [$this->mark_double_slash, $this->mark_escaped_single, $this->mark_escaped_double, "<\\/"],
            $remaining_doc_part
        );

        $end_tag_pos_in_escaped_part = stripos($escaped_remaining_part, $end_tag_to_find);

        $actual_content = '';
        if ($end_tag_pos_in_escaped_part === false) {
            $this->addError("Unclosed special tag '<{$tag_name_from_parser}>'.");
            // Consume the rest of the document as content of this tag
            $actual_content = $remaining_doc_part;
            $this->position = $this->doc_length;
        } else {
            
            $actual_content = substr($remaining_doc_part, 0, $end_tag_pos_in_escaped_part);
            
            $this->position = $content_start_pos + strlen($actual_content) + strlen($end_tag_to_find);
        }

        if ($actual_content !== '') {
            $this->dom_array[] = $actual_content;
        }
    }
}