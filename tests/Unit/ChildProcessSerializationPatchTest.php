<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

const CHILD_PROCESS_PATCH_ROOT = 'scripts/nativephp-patches/files/resources/electron/electron-plugin';

const NATIVEPHP_CHILD_PROCESS_ROOT = 'vendor/nativephp/desktop/resources/electron/electron-plugin';

it('serializes child-process API responses without Electron UtilityProcess objects', function (): void {
    foreach ([
        CHILD_PROCESS_PATCH_ROOT.'/src/server/api/childProcess.ts',
        CHILD_PROCESS_PATCH_ROOT.'/dist/server/api/childProcess.js',
    ] as $path) {
        $contents = (string) file_get_contents(base_path($path));

        expect($contents)
            ->toContain('function serializeProcess(runtimeProcess)')
            ->toContain('res.json(serializeProcess(proc));')
            ->toContain('res.json(serializeProcesses(state.processes));')
            ->not->toContain('res.json(proc);')
            ->not->toContain('res.json(state.processes);');
    }
});

it('wires the child-process source and compiled patches through apply.sh', function (): void {
    $apply = (string) file_get_contents(base_path('scripts/nativephp-patches/apply.sh'));

    expect($apply)
        ->toContain('resources/electron/electron-plugin/src/server/api/childProcess.ts')
        ->toContain('resources/electron/electron-plugin/dist/server/api/childProcess.js');
});

it('targets child-process files that still exist in the installed NativePHP package', function (): void {
    expect(base_path(NATIVEPHP_CHILD_PROCESS_ROOT.'/src/server/api/childProcess.ts'))->toBeReadableFile()
        ->and(base_path(NATIVEPHP_CHILD_PROCESS_ROOT.'/dist/server/api/childProcess.js'))->toBeReadableFile();
});
