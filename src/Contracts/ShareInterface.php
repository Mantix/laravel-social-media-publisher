<?php

namespace Mantix\LaravelSocialMediaPublisher\Contracts;

interface ShareInterface {

    public function share(string $caption, string $url): array;

}