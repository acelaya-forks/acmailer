<?php

declare(strict_types=1);

namespace AcMailer\Attachment\Parser;

use AcMailer\Attachment\Helper\AttachmentHelperTrait;
use AcMailer\Exception\InvalidAttachmentException;
use Laminas\Mime;

class MimePartAttachmentParser implements AttachmentParserInterface
{
    use AttachmentHelperTrait;

    /**
     * @param string|resource|array|Mime\Part $attachment
     * @throws InvalidAttachmentException
     */
    public function parse($attachment, ?string $attachmentName = null): Mime\Part
    {
        if (! $attachment instanceof Mime\Part) {
            throw InvalidAttachmentException::fromExpectedType(Mime\Part::class);
        }

        return $this->applyNameToPart($attachment, $attachmentName);
    }
}
