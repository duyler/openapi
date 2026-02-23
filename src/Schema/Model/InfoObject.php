<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class InfoObject implements JsonSerializable
{
    public function __construct(
        public string $title,
        public string $version,
        public ?string $description = null,
        public ?string $termsOfService = null,
        public ?Contact $contact = null,
        public ?License $license = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'title' => $this->title,
            'version' => $this->version,
        ];

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if (null !== $this->termsOfService) {
            $data['termsOfService'] = $this->termsOfService;
        }

        if (null !== $this->contact) {
            $data['contact'] = $this->contact;
        }

        if (null !== $this->license) {
            $data['license'] = $this->license;
        }

        return $data;
    }
}
