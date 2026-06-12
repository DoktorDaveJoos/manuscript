# whisper-cli binaries (speech input)

The binaries in `extras/bin/` power local speech-to-text (see
`docs/plans/speech-input-whisper.md`). They are **built offline and committed**
— the release pipeline never compiles or fetches them, so it cannot flake.
NativePHP's `extraFiles` mechanism bundles `extras/` into the app
(`Contents/extras` on macOS, beside the executable on Windows), and
electron-builder signs/notarizes the macOS binary along with the rest of the
bundle. `.github/workflows/publish.yml` fails the build if the binary is
missing or unsigned in the produced `.app`.

## Pinned upstream

- Repo: https://github.com/ggml-org/whisper.cpp
- Tag: **v1.8.6**

## macOS — `extras/bin/mac/whisper-cli` (universal2, ~6 MB)

Built from source on a Mac with Xcode CLT + cmake:

```bash
git clone --depth 1 --branch v1.8.6 https://github.com/ggml-org/whisper.cpp.git
cd whisper.cpp

# arm64 slice: Metal with the shader library embedded into the binary
cmake -B build-arm64 -DCMAKE_BUILD_TYPE=Release -DCMAKE_OSX_ARCHITECTURES=arm64 \
  -DGGML_METAL=ON -DGGML_METAL_EMBED_LIBRARY=ON -DBUILD_SHARED_LIBS=OFF \
  -DWHISPER_BUILD_TESTS=OFF -DGGML_NATIVE=OFF
cmake --build build-arm64 --config Release -j --target whisper-cli

# x86_64 slice: CPU-only (Metal on Intel Macs is not worth the flakiness),
# GGML_NATIVE=OFF for a portable instruction baseline
cmake -B build-x64 -DCMAKE_BUILD_TYPE=Release -DCMAKE_OSX_ARCHITECTURES=x86_64 \
  -DGGML_METAL=OFF -DBUILD_SHARED_LIBS=OFF \
  -DWHISPER_BUILD_TESTS=OFF -DGGML_NATIVE=OFF
cmake --build build-x64 --config Release -j --target whisper-cli

lipo -create -output whisper-cli build-arm64/bin/whisper-cli build-x64/bin/whisper-cli
```

Static (`BUILD_SHARED_LIBS=OFF`), so the single file is self-contained apart
from system frameworks. Do not strip or re-sign it locally — signing happens
in the release build.

## Windows — `extras/bin/win/` (~2.4 MB)

Taken from the official upstream release asset (not compiled by us):

- Asset: `whisper-bin-x64.zip` from the v1.8.6 release
- sha256: `b07ea0b1b4115a38e1a7b07debf581f0b77d999925f8acb8f39d322b0ba0a822`
- Files kept: `whisper-cli.exe`, `whisper.dll`, `ggml.dll`, `ggml-base.dll`,
  `ggml-cpu.dll` (the DLLs are load-time dependencies of the exe; SDL2.dll is
  only needed by the streaming examples and is omitted)

## Models

Models are **not** committed — they download on first enable, pinned by sha256
in `config/speech.php` (curated per hardware in
`App\Services\Speech\SpeechModelManager`).

## Bumping the version

1. Update the tag above, rebuild the mac universal binary, refresh the
   Windows files from the new release zip (record the new zip sha256 here).
2. Replace the files in `extras/bin/`.
3. Smoke test locally: `extras/bin/mac/whisper-cli --help`, then a real
   transcription via the app.
4. Commit the binaries together with this README change.
