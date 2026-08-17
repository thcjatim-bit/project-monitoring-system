import assert from 'node:assert/strict';
import test from 'node:test';

import {
    PHOTO_UPLOAD_LIMITS,
    compressPhoto,
    validatePhotoSelection,
} from '../../resources/js/project-photo-upload.js';

test('photo selection validates raw JPEG count and size before compression', () => {
    const valid = { name: 'survey.jpg', size: PHOTO_UPLOAD_LIMITS.maxBytes, type: 'image/jpeg' };
    const tooMany = Array.from({ length: PHOTO_UPLOAD_LIMITS.maxFiles + 1 }, () => valid);

    assert.equal(validatePhotoSelection([valid]).valid, true);
    assert.equal(validatePhotoSelection(tooMany).valid, false);
    assert.match(validatePhotoSelection(tooMany).message, /10/);
    assert.equal(
        validatePhotoSelection([{ ...valid, size: PHOTO_UPLOAD_LIMITS.maxBytes + 1 }]).valid,
        false,
    );
    assert.equal(validatePhotoSelection([{ ...valid, type: 'image/png' }]).valid, false);
});

test('compressPhoto preserves JPEG output and resizes both dimensions to the configured bounds', async () => {
    const drawCalls = [];
    const fakeBrowser = {
        FileReader: class {
            readAsDataURL() {
                this.result = 'data:image/jpeg;base64,raw';
                this.onload();
            }
        },
        Image: class {
            set src(value) {
                this.source = value;
                this.naturalWidth = 4000;
                this.naturalHeight = 2000;
                this.onload();
            }
        },
        File: class {
            constructor(parts, name, options) {
                this.parts = parts;
                this.name = name;
                this.type = options.type;
                this.lastModified = options.lastModified;
            }
        },
        document: {
            createElement() {
                return {
                    width: 1920,
                    height: 960,
                    getContext() {
                        return {
                            drawImage(...args) {
                                drawCalls.push(args);
                            },
                        };
                    },
                    toBlob(callback, mimeType, quality) {
                        assert.equal(mimeType, 'image/jpeg');
                        assert.equal(quality, PHOTO_UPLOAD_LIMITS.quality);
                        callback({ size: 123, type: mimeType });
                    },
                };
            },
        },
    };

    const output = await compressPhoto(
        { name: 'camera.jpeg' },
        PHOTO_UPLOAD_LIMITS,
        fakeBrowser,
    );

    assert.equal(output.name, 'camera.jpg');
    assert.equal(output.type, 'image/jpeg');
    assert.deepEqual(drawCalls[0].slice(-2), [1920, 960]);
});
