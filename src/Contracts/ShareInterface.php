<?php

namespace mantix\LaravelSocialMediaPublisher\Contracts;

interface ShareInterface {

    public function share(string $caption, string $url): array;

}