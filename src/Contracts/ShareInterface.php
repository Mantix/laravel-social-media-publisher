<?php

namespace Mantix\LaravelSocialMediaPublisher\Contracts;

interface ShareInterface {

    public function shareUrl(string $caption, string $url): array;

}