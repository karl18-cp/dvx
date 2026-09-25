# Employee attendance clock

Agents and team leaders use **My Attendance** for their own punches. Time in/out require a server-side face check against the signed-in account's administrator-enrolled template. Break out/in do not use the camera. The server determines the employee, shift date, and punch time, then uses the existing schedule and approved-request calculator for credited hours.

## Runtime

- Apply migrations, install npm dependencies on the PHP host, and build the frontend.
- PHP must be able to launch Node. `FACE_NODE_BINARY` can override the executable path in `config/face.php`.
- Keep the existing Human models in `public/models/human`. Inference uses local models, WASM, and Sharp; no external face API is called.
- Browser camera access requires HTTPS (localhost is also supported).
- Enrollment must use `human-3.3.6-faceres`. Templates stay encrypted at rest and are never returned to the attendance page. Captured images are processed transiently, not saved by this feature.

## Verification and limitations

Each request has a two-minute, single-use challenge bound to the account and session. Three frames must each contain exactly one sufficiently large face with matching embeddings, anti-spoofing, and liveness scores. The sequence must show front, the requested head turn, then front again. A failed, expired, or unavailable verifier records no punch. Break endpoints cannot record time in/out.

The initial matching and liveness thresholds are both 0.75. These are starting values, not a measured accuracy claim. Before operational reliance, test real enrolled employees, different employees attempting the same account, lighting/camera variation, glasses, and photo/video presentation attempts. Record false accept/reject results before changing thresholds. Browser-camera liveness is not a guarantee against sophisticated replay or synthetic video.

PHP feature tests cover authorization, session binding, replay/expiry, failed verification, schedule clamping, breaks, overnight dates, leave blocking, and template non-disclosure. The automated browser test uses a simulated camera and real PHP/Node inference to verify rejection and that no attendance is written; it does not validate successful recognition of real employees.
