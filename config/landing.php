<?php

/**
 * Default landing page copy for the home (welcome) screen.
 *
 * Admins can override most of this from the app: Dashboard → "Landing page copy"
 * (or /admin/landing). Stored overrides are merged with these defaults.
 *
 * You can still edit this file for deployment defaults; run `php artisan config:clear`
 * if you use config caching.
 */

return [

    'meta' => [
        'document_title' => 'Fake news check',
    ],

    'header' => [
        'brand' => 'FakeNews Check',
        'tagline' => 'AI-assisted screening — not legal or medical advice',
    ],

    'nav' => [
        'dashboard' => 'Dashboard',
        'login' => 'Log in',
        'register' => 'Register',
    ],

    /**
     * Display names for the active AI backend (short = badge pill, long = intro sentence).
     */
    'provider' => [
        'gemini' => [
            'short' => 'Gemini',
            'long' => 'Google Gemini',
        ],
        'groq' => [
            'short' => 'Groq',
            'long' => 'Groq',
        ],
    ],

    'hero' => [
        'badge' => 'AI-powered credibility check',
        'headline' => 'Check a story before you share it',
        // Split so the UI can show provider name + env var with spacing (see welcome.tsx).
        'intro_before_provider' => 'Paste text, a link, or a screenshot. Results run on your server via',
        'intro_after_provider' => '. Switch backends with',
        'intro_driver_env' => 'NEWS_ANALYZER_DRIVER',
        'intro_after_code' => '. No tool is 100% accurate — use judgment and trusted sources for important decisions.',
    ],

    /**
     * Rich text segments: text | code | link (link requires href + value for label).
     *
     * @var array<string, array<int, array<string, string>>>
     */
    'api_key_warning' => [
        'title' => 'API key required',
        'gemini' => [
            ['type' => 'text', 'value' => 'Set '],
            ['type' => 'code', 'value' => 'GEMINI_API_KEY'],
            ['type' => 'text', 'value' => ' in '],
            ['type' => 'code', 'value' => '.env'],
            ['type' => 'text', 'value' => ', or switch to Groq (see '],
            ['type' => 'code', 'value' => '.env.example'],
            ['type' => 'text', 'value' => ').'],
        ],
        'groq' => [
            ['type' => 'text', 'value' => 'Set '],
            ['type' => 'code', 'value' => 'GROQ_API_KEY'],
            ['type' => 'text', 'value' => ' (from '],
            ['type' => 'link', 'value' => 'console.groq.com', 'href' => 'https://console.groq.com/'],
            ['type' => 'text', 'value' => ') and '],
            ['type' => 'code', 'value' => 'NEWS_ANALYZER_DRIVER=groq'],
            ['type' => 'text', 'value' => '.'],
        ],
        'suffix' => [
            ['type' => 'text', 'value' => 'Backend: '],
            ['type' => 'code', 'value' => 'app/Services/GeminiNewsAnalyzer.php'],
            ['type' => 'text', 'value' => ' / '],
            ['type' => 'code', 'value' => 'GroqNewsAnalyzer.php'],
            ['type' => 'text', 'value' => ', route '],
            ['type' => 'code', 'value' => 'POST /analyze'],
            ['type' => 'text', 'value' => '.'],
        ],
    ],

    'input_card' => [
        'title' => 'Input',
        'description' => 'Paste text, add a URL, or upload an image.',
        'tablist_aria' => 'Input type',
        'modes' => [
            'text' => 'Text',
            'url' => 'URL',
            'image' => 'Image',
        ],
        'active_source_prefix' => 'Active source: ',
        'active_source_suffix' => '. If analysis fails, try another input type.',
        'active_source' => [
            'image_named' => 'Image (:name)',
            'url' => 'URL',
            'text' => 'Text',
            'url_empty' => 'URL (empty)',
            'image_none' => 'Image (none)',
            'text_empty' => 'Text (empty)',
        ],
        'text_label' => 'Article or claim',
        'text_placeholder' => 'Paste headline, paragraph, or social post text…',
        'url_label' => 'Page URL',
        'url_placeholder' => 'https://…',
        'url_help' => 'The server fetches the page and sends extracted text to the AI when possible.',
        'image_help' => 'Upload a screenshot of news content. Use a clear image so text can be read.',
        'image_drop' => 'Drag and drop an image here, or choose a file',
        'image_choose' => 'Choose image',
        'image_remove' => 'Remove image',
        'analyze' => 'Analyze news',
        'analyzing' => 'Analyzing…',
        'reset' => 'Reset all inputs',
    ],

    'summary_card' => [
        'title' => 'Summary',
        'description' => 'Verdict, confidence, explanation, and related topics from the AI.',
        'empty' => 'Add content on the left, then click ',
        'empty_strong' => 'Analyze news',
        'empty_after' => ' to see your verdict here.',
        'calling_ai' => 'Calling AI…',
        'verdict' => 'Verdict',
        'confidence' => 'Confidence',
        'real' => 'Real',
        'fake' => 'Fake',
        'explanation' => 'Explanation',
        'related_topics' => 'Related topics',
    ],

    'footer' => [
        'text' => 'Educational demo — not legal or medical advice. AI outputs can be wrong or biased; verify important claims with primary sources.',
    ],

    'toasts' => [
        'analysis_complete' => 'Analysis complete',
        'api_key_missing_gemini' => 'Add GEMINI_API_KEY to your .env file (see .env.example).',
        'api_key_missing_groq' => 'Add GROQ_API_KEY to your .env file (see .env.example). Get a key at console.groq.com.',
        'generic_error' => 'Something went wrong',
    ],
];
