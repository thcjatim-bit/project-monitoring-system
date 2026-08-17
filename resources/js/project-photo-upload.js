export const PHOTO_UPLOAD_LIMITS = Object.freeze({
    maxFiles: 10,
    maxBytes: 5 * 1024 * 1024,
    maxWidth: 1920,
    maxHeight: 1080,
    quality: 0.82,
});

function withLimits(limits = PHOTO_UPLOAD_LIMITS) {
    return { ...PHOTO_UPLOAD_LIMITS, ...(limits ?? {}) };
}

export function validatePhotoSelection(files, limits = PHOTO_UPLOAD_LIMITS) {
    const selectedFiles = Array.from(files ?? []);
    const resolvedLimits = withLimits(limits);

    if (selectedFiles.length === 0) {
        return { valid: false, message: 'Pilih setidaknya satu foto JPEG.' };
    }

    if (selectedFiles.length > resolvedLimits.maxFiles) {
        return {
            valid: false,
            message: `Pilih maksimal ${resolvedLimits.maxFiles} foto dalam satu unggahan.`,
        };
    }

    if (selectedFiles.some((file) => file?.type !== 'image/jpeg')) {
        return { valid: false, message: 'Semua file harus berupa JPEG.' };
    }

    if (selectedFiles.some((file) => file?.size > resolvedLimits.maxBytes)) {
        return {
            valid: false,
            message: `Ukuran mentah setiap foto maksimal ${Math.round(resolvedLimits.maxBytes / 1024 / 1024)} MB.`,
        };
    }

    return { valid: true, message: '' };
}

export function compressPhoto(file, limits = PHOTO_UPLOAD_LIMITS, browser = globalThis) {
    const resolvedLimits = withLimits(limits);
    const runtime = browser ?? globalThis;
    const FileReaderConstructor = runtime.FileReader;
    const ImageConstructor = runtime.Image;
    const document = runtime.document;
    const FileConstructor = runtime.File ?? globalThis.File;

    if (!FileReaderConstructor || !ImageConstructor || !document || !FileConstructor) {
        return Promise.reject(new Error('Browser tidak mendukung kompresi foto JPEG.'));
    }

    return new Promise((resolve, reject) => {
        const reader = new FileReaderConstructor();

        reader.onerror = () => reject(reader.error || new Error('Foto tidak dapat dibaca.'));
        reader.onload = () => {
            const image = new ImageConstructor();

            image.onerror = () => reject(new Error('Foto JPEG tidak valid.'));
            image.onload = () => {
                try {
                    const sourceWidth = image.naturalWidth || image.width;
                    const sourceHeight = image.naturalHeight || image.height;

                    if (!sourceWidth || !sourceHeight) {
                        reject(new Error('Dimensi foto tidak dapat dibaca.'));
                        return;
                    }

                    const ratio = Math.min(
                        1,
                        resolvedLimits.maxWidth / sourceWidth,
                        resolvedLimits.maxHeight / sourceHeight,
                    );
                    const width = Math.max(1, Math.round(sourceWidth * ratio));
                    const height = Math.max(1, Math.round(sourceHeight * ratio));
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');

                    if (!context) {
                        reject(new Error('Foto gagal diproses oleh browser.'));
                        return;
                    }

                    canvas.width = width;
                    canvas.height = height;
                    context.drawImage(image, 0, 0, width, height);
                    canvas.toBlob((blob) => {
                        if (!blob) {
                            reject(new Error('Foto gagal dikompres.'));
                            return;
                        }

                        const originalName = typeof file?.name === 'string' && file.name
                            ? file.name
                            : 'photo.jpg';
                        const name = originalName.replace(/\.[^.]+$/, '') + '.jpg';
                        const lastModified = Number.isFinite(file?.lastModified)
                            ? file.lastModified
                            : Date.now();

                        resolve(new FileConstructor([blob], name, {
                            type: 'image/jpeg',
                            lastModified,
                        }));
                    }, 'image/jpeg', resolvedLimits.quality);
                } catch (error) {
                    reject(error instanceof Error ? error : new Error('Foto gagal dikompres.'));
                }
            };

            image.src = reader.result;
        };

        try {
            reader.readAsDataURL(file);
        } catch (error) {
            reject(error instanceof Error ? error : new Error('Foto tidak dapat dibaca.'));
        }
    });
}

function setUploadState(form, status, state, message = '') {
    form.dataset.photoState = state;

    if (!status) {
        return;
    }

    status.hidden = message === '';
    status.textContent = message;
    status.setAttribute('role', state === 'error' ? 'alert' : 'status');
}

function bindPhotoUpload(form, browser) {
    if (form.dataset.photoUploadBound === 'true') {
        return;
    }

    const input = form.querySelector('[data-photo-input]');
    if (!input) {
        return;
    }

    const status = form.querySelector('[data-photo-upload-status]');
    const submitButton = form.querySelector('button[type="submit"]');
    form.dataset.photoUploadBound = 'true';
    setUploadState(form, status, 'idle');

    input.addEventListener('change', () => {
        const validation = validatePhotoSelection(input.files);

        input.setCustomValidity(validation.valid ? '' : validation.message);
        setUploadState(
            form,
            status,
            validation.valid ? 'ready' : 'error',
            validation.valid ? `${input.files.length} foto siap diunggah.` : validation.message,
        );
    });

    form.addEventListener('submit', async (event) => {
        if (form.dataset.compressed === 'true') {
            return;
        }

        event.preventDefault();
        const rawFiles = Array.from(input.files ?? []);
        const validation = validatePhotoSelection(rawFiles);

        if (!validation.valid) {
            input.setCustomValidity(validation.message);
            setUploadState(form, status, 'error', validation.message);
            input.reportValidity?.();
            return;
        }

        input.setCustomValidity('');
        setUploadState(form, status, 'loading', 'Mengompres foto sebelum diunggah…');
        form.setAttribute('aria-busy', 'true');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const compressedFiles = [];
            for (const file of rawFiles) {
                compressedFiles.push(await compressPhoto(file, PHOTO_UPLOAD_LIMITS, browser));
            }

            const DataTransferConstructor = browser.DataTransfer ?? globalThis.DataTransfer;
            if (!DataTransferConstructor) {
                throw new Error('Browser tidak mendukung pengiriman beberapa foto.');
            }

            const transfer = new DataTransferConstructor();
            compressedFiles.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
            form.dataset.compressed = 'true';
            setUploadState(form, status, 'submitting', 'Foto siap diunggah.');
            form.submit();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Foto gagal diproses.';
            input.setCustomValidity(message);
            setUploadState(form, status, 'error', message);
            input.reportValidity?.();
        } finally {
            form.removeAttribute('aria-busy');
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });
}

export function initializePhotoUploads(document = globalThis.document, browser = globalThis) {
    if (!document?.querySelectorAll) {
        return;
    }

    const bind = () => document.querySelectorAll('[data-photo-upload]').forEach(
        (form) => bindPhotoUpload(form, browser),
    );

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind, { once: true });
        return;
    }

    bind();
}
