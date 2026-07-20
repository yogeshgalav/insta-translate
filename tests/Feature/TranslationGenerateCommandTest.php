<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use InstaRequest\InstaTranslate\Console\Commands\TranslationGenerateCommand;

it('does not expose a package-owned default_model config key', function () {
    expect(config()->has('insta-translate.default_model'))->toBeFalse();
    expect(config('insta-translate'))->not->toHaveKey('default_model');
});

it('registers translation:generate with provider and model options', function () {
    expect(Artisan::all())->toHaveKey('translation:generate');

    /** @var TranslationGenerateCommand $command */
    $command = Artisan::all()['translation:generate'];
    $definition = $command->getDefinition();

    expect($definition->hasOption('provider'))->toBeTrue()
        ->and($definition->hasOption('model'))->toBeTrue();
});
