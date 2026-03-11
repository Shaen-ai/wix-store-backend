<?php

namespace App\Contracts;

interface ImageTo3DProvider
{
    /**
     * Submit one or more images for 3D model generation (multi-view supported).
     *
     * @param array<string> $imagePaths Absolute paths to image files (1-4 images from different views).
     * @param string|null $texturePrompt Optional text (e.g. title + description) to guide the AI on which item to model. Max 600 chars.
     * @return string The provider's job/task ID.
     */
    public function submit(array $imagePaths, ?string $texturePrompt = null): string;

    /**
     * Poll the status of a generation job.
     *
     * @param string $jobId
     * @return array{status: string, glb_download_url: ?string}
     *   status: 'queued'|'processing'|'done'|'failed'
     */
    public function poll(string $jobId): array;
}
