// --- Example using custom validators and srcset ---

$customConfig = [
    'attributes' => [
        'img' => ['src', 'srcset', 'width', 'data-id'],
    ],
    'validators' => [
        'img' => [
            // Custom validator for the 'width' attribute on <img> tags
            'width' => function($value) {
                // Ensure width is a digit and not larger than 500
                if (ctype_digit($value) && (int)$value <= 500) {
                    return $value;
                }
                return '500'; // Return a default/safe value
            },
            // Custom validator for a specific data attribute
            'data-id' => function($value) {
                // Allow only alphanumeric IDs
                return preg_replace('/[^a-zA-Z0-9-]/', '', $value);
            }
        ]
    ],
    'allow_protocol_relative' => true, // Explicitly allow for this instance
];

$dirtyHtml = <<<HTML
<img src="/logo.png"
     width="900"
     data-id="user-profile-123!@#"
     srcset="
        //example.com/small.jpg 300w,
        javascript:alert(1) 600w,
        /large.jpg 1000w
     ">
HTML;

$sanitizer = new HtmlSanitizer($customConfig);
$cleanHtml = $sanitizer->sanitize($dirtyHtml);

echo $cleanHtml;

/*
Expected Clean Output:

<img src="/logo.png" width="500" data-id="user-profile-123" srcset="//example.com/small.jpg 300w, /large.jpg 1000w">

Breakdown of what happened:
- `width="900"` was changed to `width="500"` by the custom validator.
- `data-id` had special characters stripped by its validator.
- `srcset` had the javascript: URL removed, but the protocol-relative and safe relative URLs were kept.
*/