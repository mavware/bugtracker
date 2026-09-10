{{-- The sample room: a living room at night with one trail drawing itself across
     it, the way the report's replay walks a real night. The welcome page shows a
     finished report with it; the capture page stands it in for a camera that has
     not opened yet. Decorative wherever it appears — the caller owns the box it
     sits in, any caption over it, and the aria-hidden. --}}
@php
    /**
     * The night's one trail: in at the left edge, a loop over the rug, and up to the
     * top-right corner, which is the corner Start watching sits in. It is a cue as
     * much as a picture, so it is drawn in the accent rather than in the replay's
     * per-track hues — one line the eye can follow to the button, instead of three
     * finished ones competing.
     */
    $trail = 'M 2 176 C 60 186, 130 180, 186 158 C 232 140, 288 138, 296 116 C 304 94, 258 84, 222 98 C 190 110, 186 142, 214 154 C 246 168, 300 148, 326 118 C 344 96, 354 64, 356 32';

    // Per instance: two of these on one page would otherwise both paint with
    // whichever <defs> the browser met first.
    $lamplightId = uniqid('sample-room-lamplight-');
@endphp

<svg viewBox="0 0 400 200" fill="none" {{ $attributes->class(['block size-full']) }}>
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
         steps of it and the trails above are the brightest thing in the picture.
         Everything but the floor keeps the size it had in the taller frame: the
         third that came off the height came out of the floor's depth, so the
         furniture moved up rather than being squashed. --}}
    <rect width="400" height="200" fill="#18181b" />
    <rect width="400" height="78" fill="#202024" />
    <rect y="74" width="400" height="6" fill="#2a2a30" />

    {{-- The lamp's pool of light, warm and low. --}}
    <ellipse cx="354" cy="121" rx="150" ry="75" fill="url(#{{ $lamplightId }})" />

    {{-- Doorway, left: an unlit hall, so darker than the wall it is in. --}}
    <rect x="12" y="8" width="44" height="68" rx="2" fill="#131316" stroke="#2c2c33" />

    {{-- Rug and coffee table, well up the floor: the near half of the room is
         what the shorter frame gave up. --}}
    <ellipse cx="222" cy="149" rx="118" ry="32" fill="#212126" stroke="#2a2a30" />
    <rect x="176" y="128" width="96" height="26" rx="4" fill="#2b2b32" stroke="#3a3a43" />
    <path d="M 182 154 v 10 M 266 154 v 10" stroke="#3a3a43" stroke-width="2" />

    {{-- Sofa, against the back wall: up by exactly what the wall lost, so it sits
         on the skirting the same way it did before. --}}
    <rect x="72" y="48" width="118" height="36" rx="6" fill="#27272d" />
    <rect x="66" y="78" width="130" height="30" rx="6" fill="#2e2e35" />
    <rect x="58" y="62" width="16" height="48" rx="5" fill="#25252b" />
    <rect x="188" y="62" width="16" height="48" rx="5" fill="#25252b" />
    <path d="M 110 80 v 26 M 152 80 v 26" stroke="#3a3a43" stroke-width="1.5" />

    {{-- Media unit, television and the floor lamp lighting the room. --}}
    <rect x="262" y="74" width="94" height="26" rx="3" fill="#27272d" stroke="#36363e" />
    <rect x="286" y="30" width="62" height="40" rx="2" fill="#141418" stroke="#33333b" />
    <path d="M 317 70 v 4" stroke="#33333b" stroke-width="3" />
    <path d="M 376 96 v 58" stroke="#33333b" stroke-width="3" />
    <ellipse cx="376" cy="156" rx="13" ry="5" fill="#2b2b32" />
    <path d="M 362 96 L 390 96 L 384 74 L 368 74 Z" fill="#5a4a2c" stroke="#6f5c36" />

    {{-- Where the trail comes in, as the report marks an entry zone. There is no
         exit bar to match it: the trail stops inside the frame under the button
         rather than leaving the room. --}}
    <rect x="0" y="163" width="4" height="26" fill="rgb(74 222 128 / 0.65)" />

    {{-- Drawn once over a second rather than sitting there finished: pathLength="1"
         makes the dash pattern one path-length long, so animating the offset from 1
         to 0 walks the line from the entry dot to the tip with no measuring. Without
         motion-safe the animation is dropped and the dashoffset default of 0 leaves
         the whole trail on screen, which is the right still picture. --}}
    <g data-test="sample-room-trail" class="text-accent">
        <path
            d="{{ $trail }}"
            pathLength="1"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-dasharray="1"
            class="motion-safe:animate-trail"
        />
        <circle cx="2" cy="176" r="9" fill="currentColor" opacity="0.2" />
        <circle cx="2" cy="176" r="4" fill="currentColor" />
        <path d="M 356 18 L 350 32 L 362 32 Z" fill="currentColor" class="motion-safe:animate-trail-tip" />
    </g>
</svg>
