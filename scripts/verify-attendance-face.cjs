// Server-only inference. Never accepts a client descriptor, similarity score, or liveness result.
const fs = require('node:fs/promises');
const path = require('node:path');
const { fileURLToPath, pathToFileURL } = require('node:url');
const sharp = require('sharp');
const { Human } = require(path.join(path.dirname(require.resolve('@vladmandic/human')), 'human.node-wasm.js'));

async function main() {
    let input = '';
    for await (const chunk of process.stdin) { input += chunk; if (input.length > 1800000) throw new Error('input too large'); }
    const data = JSON.parse(input);
    if (!Array.isArray(data.frames) || data.frames.length !== 3 || !Array.isArray(data.descriptor) || data.descriptor.length < 128 || data.descriptor.some(v => !Number.isFinite(v)) || !['left', 'right'].includes(data.direction)) throw new Error('invalid input');
    const human = new Human({
        backend: 'wasm', debug: false, async: false, cacheModels: false, cacheSensitivity: 0, skipAllowed: false,
        wasmPath: path.dirname(require.resolve('@tensorflow/tfjs-backend-wasm/package.json')).replaceAll('\\', '/') + '/dist/',
        modelBasePath: pathToFileURL(path.resolve(__dirname, '../public/models/human/')).href + '/',
        filter: { enabled: true, return: false, equalization: false },
        face: { enabled: true, detector: { maxDetected: 2, minConfidence: 0.8, rotation: true, skipFrames: 0, skipTime: 0 }, mesh: { enabled: true }, description: { enabled: true, skipFrames: 0, skipTime: 0 }, antispoof: { enabled: true, skipFrames: 0, skipTime: 0 }, liveness: { enabled: true, skipFrames: 0, skipTime: 0 }, emotion: { enabled: false }, iris: { enabled: false }, attention: { enabled: false }, gear: { enabled: false } },
        body: { enabled: false }, hand: { enabled: false }, object: { enabled: false }, gesture: { enabled: false }, segmentation: { enabled: false },
    });
    human.tf.io.registerLoadRouter(url => typeof url === 'string' && url.startsWith('file:') ? { load: async () => {
        const filename = fileURLToPath(url);
        const model = JSON.parse(await fs.readFile(filename, 'utf8'));
        const specs = [], chunks = [];
        for (const group of model.weightsManifest) {
            specs.push(...group.weights);
            for (const name of group.paths) chunks.push(await fs.readFile(path.join(path.dirname(filename), name)));
        }
        const buffer = Buffer.concat(chunks);
        return { modelTopology: model.modelTopology, weightSpecs: specs, weightData: buffer.buffer.slice(buffer.byteOffset, buffer.byteOffset + buffer.byteLength), format: model.format, generatedBy: model.generatedBy, convertedBy: model.convertedBy };
    } } : null);
    await human.load();
    if (human.models.loaded().length < 5) throw new Error('models unavailable');
    const faces = [];
    for (const frame of data.frames) {
        if (typeof frame !== 'string' || !/^data:image\/jpeg;base64,[A-Za-z0-9+/=]+$/.test(frame) || frame.length > 500000) throw new Error('invalid frame');
        const bytes = Buffer.from(frame.split(',')[1], 'base64');
        const { data: pixels, info } = await sharp(bytes, { limitInputPixels: 1280 * 960 }).removeAlpha().toColourspace('srgb').raw().toBuffer({ resolveWithObject: true });
        if (info.width < 320 || info.height < 240 || info.channels !== 3) throw new Error('invalid dimensions');
        const tensor = human.tf.tensor3d(new Uint8Array(pixels), [info.height, info.width, 3], 'int32');
        let result;
        try { result = await human.detect(tensor); } finally { tensor.dispose(); }
        if (result.face.length !== 1) return { accepted: false, reason: 'verification_failed' };
        const face = result.face[0];
        const similarity = face.embedding ? human.match.similarity(face.embedding, data.descriptor) : NaN;
        if (!face.embedding || face.embedding.some(v => !Number.isFinite(v)) || face.embedding.length !== data.descriptor.length || !Number.isFinite(similarity) || !Number.isFinite(face.real) || !Number.isFinite(face.live) || face.real < data.minimumLiveness || face.live < data.minimumLiveness || face.box[2] < 100 || face.box[3] < 100 || similarity < data.minimumSimilarity) return { accepted: false, reason: 'verification_failed' };
        const yaw = face.rotation?.angle?.yaw;
        if (!Number.isFinite(yaw)) return { accepted: false, reason: 'verification_failed' };
        faces.push(yaw);
    }
    const [start, turn, end] = faces;
    // Human yaw is negative for the person's left in the unmirrored camera frames.
    const movement = data.direction === 'left' ? start - turn : turn - start;
    return { accepted: Math.abs(start) < 0.25 && Math.abs(end) < 0.25 && movement > 0.18 && Math.abs(turn - end) > 0.18, reason: 'verification_failed' };
}
main().then(result => process.stdout.write(JSON.stringify(result))).catch(() => { process.stdout.write(JSON.stringify({ accepted: false, reason: 'unavailable' })); process.exitCode = 1; });
