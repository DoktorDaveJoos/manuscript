# Speech Input via Local Whisper (whisper.cpp)

Status: implemented (2026-06-12)

## Goal

Optional speech-to-text on all AI chat inputs (plot coach, editor chat drawer, editorial
review chat). Fully local: a bundled whisper.cpp binary plus a Whisper model downloaded
on first enable. No cloud STT, no API key, works offline.

## Decisions (approved)

- **Engine**: whisper.cpp CLI binary, bundled per-platform, signed with the app.
  Chosen over transformers.js (quality/perf) and Apple Speech (Windows parity).
- **Model strategy**: curated by hardware, no picker.
  - Apple Silicon (arm64): `ggml-large-v3-turbo-q5_0.bin` (~574 MB) — Metal, faster than realtime.
  - Intel mac / Windows x64: `ggml-small-q5_1.bin` (~182 MB) — responsive on CPU.
  - Downloaded from Hugging Face (`ggerganov/whisper.cpp`), sha256-pinned in `config/speech.php`.
- **Scope v1**: mic button on the shared `AiChatInput` component only.
  Out of scope: TipTap prose dictation, live/streaming transcription, model picker, cloud fallback.
- **Enabled state**: file presence is the source of truth — no DB column, no migration.
  Mic renders when the model file exists and is valid; deleting the model disables the feature.

## Architecture

### Frontend

- `useSpeechInput()` hook + mic button in `AiChatInput.tsx`.
- Click to record (pulsing state), click to stop, Esc cancels, ~2 min cap.
- `getUserMedia` → `MediaRecorder` (webm/opus) → decode + resample to **16 kHz mono WAV**
  in the renderer via `OfflineAudioContext` (this removes any ffmpeg need) → POST.
- Transcript is appended to the textarea; user reviews before sending.
- Mic visible only when the speech model is ready (status via shared Inertia prop).
- Mic permission: first `getUserMedia` triggers the native prompt. Denied → toast pointing
  at System Settings.

### Backend

- `SpeechTranscriptionController` — POST multipart WAV → `{ text }`.
- `SpeechModelController` — start download / poll progress / delete model.
- `WhisperTranscriber` service — `Process::run()` on the bundled binary, `-l auto`
  (German/English auto-detect), timeout, stdout = transcript.
- Binary path: NativePHP `extras` disk (`NATIVEPHP_EXTRAS_PATH`), falling back to
  `base_path('extras')` for plain `artisan serve` dev.
- Model storage: `app_data` disk under `speech/`. Download streams via `Http::sink()`,
  progress to cache, sha256 verified, atomic rename. Failure → partial file deleted, retry state.
- Download runner: queued job if the packaged app runs a queue worker (verify in
  `config/nativephp.php` / vendor); otherwise a detached `speech:download-model` artisan
  process. Same flow either way.
- Settings UI: "Speech input" section on the AI settings page — download button with size,
  progress bar, downloaded state with delete.

## Release pipeline (kept safe)

- Binaries committed to the repo (built **offline, once**, at a pinned whisper.cpp tag):
  - `extras/bin/mac/whisper-cli` — universal arm64+x86_64, Metal embedded, ~6–8 MB.
  - `extras/bin/win/whisper-cli.exe` — ~3 MB (from the pinned upstream release).
  - Build recipe in `scripts/whisper-binary/README.md`.
- Ships via NativePHP's first-party `extraFiles` mechanism (`<app>/extras/` →
  `Contents/extras` on mac, beside the exe on win). **No vendor patch needed for bundling**;
  PHP locates it via `NATIVEPHP_EXTRAS_PATH`.
- One vendor patch: `build/entitlements.mac.plist` gains
  `com.apple.security.device.audio-input` — whole-file copy via the existing
  `scripts/nativephp-patches/apply.sh` mechanism. Guard test asserts the key is present
  in the patch file. (`NSMicrophoneUsageDescription` already ships in NativePHP's default
  electron-builder config.)
- `publish.yml` artifact guard extended: whisper-cli present in the built `.app` and
  `codesign --verify` passes.
- Accepted wart: `extras/` ships whole to both platforms (~3 MB of the other OS's binary).
- Updater untouched: bundle grows ~6 MB; the model never enters the pipeline.

## Failure modes

| Failure | Behavior |
| --- | --- |
| Binary missing (pipeline regression) | Mic hidden; app unaffected |
| Model missing | Mic hidden; settings shows download CTA |
| whisper-cli error / timeout | Toast; recording discarded |
| Download failure / sha mismatch | Partial file deleted; error state with retry |
| Mic permission denied | Toast with System Settings pointer |

## Testing

- Feature tests for both controllers (`Process::fake`, `Storage::fake`) — upload validation,
  transcription response, download lifecycle, hardware curation.
- `tests/Browser/SpeechInputTest.php` — new-feature browser test using Chromium's
  `--use-fake-device-for-media-stream` against a faked backend.
- Guard test: entitlements patch file contains the audio-input entitlement.
- Optional local-only smoke test (skipped unless binary + model exist) transcribing a
  short fixture WAV (generated with macOS `say`).
