<?php

namespace mantix\LaravelSocialMediaPublisher\Contracts;

interface ShareDocumentPostInterface {

    public function shareDocument(string $caption, string $document_url): array;

}