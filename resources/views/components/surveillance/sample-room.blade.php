{{-- The sample room: a living room at night with three trails crawling across it,
     drawn the way the report's replay draws a real night. The welcome page shows a
     finished report with it; the capture page stands it in for a camera that has
     not opened yet. Decorative wherever it appears — the caller owns the box it
     sits in, any caption over it, and the aria-hidden. --}}
@php
    /**
     * Three sample trails across the room, drawn the way the report's replay draws
     * real ones: the hue sequence is the replay's (index * 67 degrees), with entry
     * dots and exit arrowheads, and green/red bars where each trail crosses the
     * frame edge. Saturation and lightness are the replay's own 85%/60% too — the
     * room below is drawn at night, as the dimmed photo the replay lays its trails
     * over, so nothing has to be pulled down to read.
     *
     * @var array<int, array{d: string, color: string, delay: string, start: array{int, int}, arrow: string}>
     */
    $trails = [
        [
            'd' => 'M 2 172 C 44 168, 66 190, 108 182 S 178 158, 216 152 S 306 152, 392 158',
            'color' => 'hsl(0 85% 60%)',
            'delay' => '0s',
            'start' => [2, 172],
            'arrow' => 'M 397 158 L 383 153 L 383 164 Z',
        ],
        [
            'd' => 'M 150 298 C 152 266, 178 250, 214 236 S 268 208, 252 186 S 196 176, 168 196 S 120 232, 76 240 L 4 248',
            'color' => 'hsl(134 85% 60%)',
            'delay' => '-1.5s',
            'start' => [150, 298],
            'arrow' => 'M 0 249 L 15 244 L 15 255 Z',
        ],
        [
            'd' => 'M 32 102 C 30 124, 44 140, 64 148 S 120 158, 168 166 S 250 200, 292 236 S 340 276, 356 294',
            'color' => 'hsl(201 85% 60%)',
            'delay' => '-3s',
            'start' => [32, 102],
            'arrow' => 'M 358 298 L 347 289 L 356 284 Z',
        ],
    ];

    // Per instance: two of these on one page would otherwise both paint with
    // whichever <defs> the browser met first.
    $lamplightId = uniqid('sample-room-lamplight-');
@endphp

<svg viewBox="0 0 400 300" fill="none" {{ $attributes->class(['block size-full']) }}>
    <defs>
        {{-- The lamplight falls off rather than ending: a hard-edged ellipse here
             reads as a second rug, not as light. --}}
        <radialGradient id="{{ $lamplightId }}">
            <stop offset="0" stop-color="#a8a29e" stop-opacity="0.30" />
            <stop offset="0.55" stop-color="#a8a29e" stop-opacity="0.13" />
            <stop offset="1" stop-color="#a8a29e" stop-opacity="0" />
        </radialGradient>
    </defs>

    {{-- The reference frame: a living room at night, from a camera propped on a
         shelf. The room is drawn on the replay's own backdrop (#18181b) and lit
         only by the floor lamp on the right, so the palette stays within a few
         steps of it and the trails above are the brightest thing in the picture. --}}
    <rect width="400" height="300" fill="#18181b" />
    <rect width="400" height="100" fill="#202024" />
    <rect y="96" width="400" height="6" fill="#2a2a30" />

    {{-- The lamp's pool of light, warm and low. --}}
    <ellipse cx="354" cy="170" rx="150" ry="112" fill="url(#{{ $lamplightId }})" />

    {{-- Doorway, left: an unlit hall, so darker than the wall it is in. --}}
    <rect x="12" y="12" width="44" height="84" rx="2" fill="#131316" stroke="#2c2c33" />

    {{-- Rug and coffee table. --}}
    <ellipse cx="222" cy="216" rx="118" ry="52" fill="#212126" stroke="#2a2a30" />
    <rect x="176" y="196" width="96" height="26" rx="4" fill="#2b2b32" stroke="#3a3a43" />
    <path d="M 182 222 v 10 M 266 222 v 10" stroke="#3a3a43" stroke-width="2" />

    {{-- Sofa, against the back wall. --}}
    <rect x="72" y="70" width="118" height="36" rx="6" fill="#27272d" />
    <rect x="66" y="100" width="130" height="30" rx="6" fill="#2e2e35" />
    <rect x="58" y="84" width="16" height="48" rx="5" fill="#25252b" />
    <rect x="188" y="84" width="16" height="48" rx="5" fill="#25252b" />
    <path d="M 110 102 v 26 M 152 102 v 26" stroke="#3a3a43" stroke-width="1.5" />

    {{-- Media unit, television and the floor lamp lighting the room. --}}
    <rect x="262" y="96" width="94" height="26" rx="3" fill="#27272d" stroke="#36363e" />
    <rect x="286" y="52" width="62" height="40" rx="2" fill="#141418" stroke="#33333b" />
    <path d="M 317 92 v 4" stroke="#33333b" stroke-width="3" />
    <path d="M 376 118 v 78" stroke="#33333b" stroke-width="3" />
    <ellipse cx="376" cy="198" rx="13" ry="5" fill="#2b2b32" />
    <path d="M 362 118 L 390 118 L 384 96 L 368 96 Z" fill="#5a4a2c" stroke="#6f5c36" />

    {{-- Where the trails cross the frame edge, as the report marks them. --}}
    <rect x="0" y="160" width="4" height="26" fill="rgb(74 222 128 / 0.65)" />
    <rect x="136" y="296" width="32" height="4" fill="rgb(74 222 128 / 0.65)" />
    <rect x="396" y="144" width="4" height="28" fill="rgb(248 113 113 / 0.65)" />
    <rect x="0" y="236" width="4" height="26" fill="rgb(248 113 113 / 0.65)" />
    <rect x="340" y="296" width="34" height="4" fill="rgb(248 113 113 / 0.65)" />

    @foreach ($trails as $trail)
        <g data-test="sample-room-trail">
            {{-- The settled trail, with the crawling dashes of the replay on top of it. --}}
            <path d="{{ $trail['d'] }}" stroke="{{ $trail['color'] }}" stroke-width="2.5" stroke-linecap="round" opacity="0.3" />
            <path
                d="{{ $trail['d'] }}"
                stroke="{{ $trail['color'] }}"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-dasharray="6 6"
                style="animation-delay: {{ $trail['delay'] }}"
                class="motion-safe:animate-trail"
            />
            <circle cx="{{ $trail['start'][0] }}" cy="{{ $trail['start'][1] }}" r="9" fill="{{ $trail['color'] }}" opacity="0.2" />
            <circle cx="{{ $trail['start'][0] }}" cy="{{ $trail['start'][1] }}" r="4" fill="{{ $trail['color'] }}" />
            <path d="{{ $trail['arrow'] }}" fill="{{ $trail['color'] }}" />
        </g>
    @endforeach
</svg>
