<?php

namespace Mantix\LaravelSocialMediaPublisher\Contracts;

interface ShareVideoPostInterface {

    public function shareVideo(string $caption, string $video_url): array;

}