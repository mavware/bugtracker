<?php

use App\Actions\Surveillance\WatchConfig;
use App\Http\Controllers\Surveillance\WatchController;
use App\Models\User;

// The guest pages are shells the browser fills in. What matters server-side is
// that they open without an account, hand the scripts the right config, and
// carry the same hooks the logged-in capture page does.
test('a guest can open the watch page and is told the night stays on the device', function () {
    $this->get(route('watch.capture'))
        ->assertOk()
        ->assertSee('data-test="start-capture-button"', false)
        ->assertSee('data-test="check-camera-button"', false)
        ->assertSee('data-capture="check-label"', false)
        ->assertSee('data-test="end-session-button"', false)
        ->assertDontSee('data-test="toggle-discarded-button"', false)
        ->assertSee('id="local-nights"', false)
        ->assertSee('stay in this browser')
        ->assertSee('If the screen keeps sleeping')
        ->assertDontSee('Checking on it from bed');
});

test('a guest can open a local report shell for any uuid', function () {
    $this->get(route('watch.report', ['localId' => '4f1a9d0e-7b7d-4c1e-9d5e-3f0a1b2c3d4e']))
        ->assertOk()
        ->assertSee('data-local-id="4f1a9d0e-7b7d-4c1e-9d5e-3f0a1b2c3d4e"', false)
        ->assertSee('data-report="canvas"', false)
        ->assertSee('data-report="row-template"', false)
        ->assertSee('data-test="toggle-discarded-button"', false)
        ->assertSee('data-report="discard-label"', false);
});

test('a report id that is not a uuid is not a page', function () {
    $this->get('/watch/not-a-uuid/report')->assertNotFound();
});

/**
 * Asserted exactly, both ways, for the same reason CapturePageTest does: the
 * only consumers are the watch scripts, which these tests cannot execute.
 */
test('the watch config carries exactly what the watch scripts read', function () {
    $config = app(WatchConfig::class)();

    expect(array_keys($config))->toBe(['mode', 'authenticated', 'csrfToken', 'routes'])
        ->and($config['mode'])->toBe('local')
        ->and($config['authenticated'])->toBeFalse()
        ->and(array_keys($config['routes']))->toBe(['report', 'watch', 'import', 'register'])
        ->and($config['routes']['report'])->toBe(route('watch.report', ['localId' => WatchController::LOCAL_ID_PLACEHOLDER]))
        ->and($config['routes']['import'])->toBe(route('surveillance.import'));
});

test('a logged-in visitor is recognised so the page can offer to save nights to the account', function () {
    $this->actingAs(User::factory()->create());

    expect(app(WatchConfig::class)()['authenticated'])->toBeTrue();

    $this->get(route('watch.capture'))
        ->assertOk()
        ->assertSee('data-nights="claim-all"', false)
        ->assertSee('saved on this device only');
});

// What is kept, where it is kept and what removes it used to be said twice: once
// in the setup advice, which capture.js hides as soon as watching starts, and once
// in the box that stays. The component's box is the one that survives the night.
test('the guest page makes its storage claim once', function () {
    $content = $this->get(route('watch.capture'))->assertOk()->getContent();

    expect(substr_count($content, 'Nothing leaves this device'))->toBe(1)
        ->and(substr_count($content, 'Detection runs entirely in this browser'))->toBe(1);
});

test('a guest is offered an account rather than an import', function () {
    $this->get(route('watch.capture'))
        ->assertOk()
        ->assertDontSee('data-nights="claim-all"', false)
        ->assertSee(route('register'));
});

// capture.js makes [data-app-nav] inert while recording. The watch page has two
// such regions: its header, and the paragraph offering an account — following
// either link mid-night would end the night.
test('the watch chrome and the sign-up offer carry the hook capture.js locks while recording', function () {
    $page = $this->get(route('watch.capture'));

    preg_match_all('/<[a-z-]+[^>]*\sdata-app-nav[=\s>]/i', $page->getContent(), $marked);
    preg_match('/<p[^>]*\sdata-app-nav[^>]*>(.*?)<\/p>/s', $page->getContent(), $offer);

    expect($marked[0])->toHaveCount(2)
        ->and($offer[1])->toContain(route('register'));
});
