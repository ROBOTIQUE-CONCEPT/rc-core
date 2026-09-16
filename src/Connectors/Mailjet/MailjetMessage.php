<?php

declare(strict_types=1);



namespace WPRC\Core\Connectors\Mailjet;



defined('ABSPATH') || exit;





/**

 * Normalized message payload used by the Mailjet API provider.

 */

final class MailjetMessage

{

    /** @var array<int,array{Email:string,Name:string}> */

    private array $to = [];



    /** @var array<int,array{Email:string,Name:string}> */

    private array $cc = [];



    /** @var array<int,array{Email:string,Name:string}> */

    private array $bcc = [];



    /** @var array{Email:string,Name:string}|null */

    private ?array $from = null;



    /** @var array{Email:string,Name:string}|null */

    private ?array $replyTo = null;



    /** @var array<int,array{ContentType:string,Filename:string,Base64Content:string}> */

    private array $attachments = [];



    /** @var array<string,mixed> */

    private array $variables = [];



    private string $subject = '';

    private string $textPart = '';

    private string $htmlPart = '';

    private int $templateId = 0;

    private bool $templateLanguage = true;



    /** @param array{Email:string,Name?:string} $from */

    public function setFrom(array $from): self

    {

        $email = sanitize_email((string) ($from['Email'] ?? ''));

        $name = sanitize_text_field((string) ($from['Name'] ?? ''));



        if ($email !== '' && is_email($email)) {

            $this->from = ['Email' => $email, 'Name' => $name];

        }



        return $this;

    }



    /** @param array{Email:string,Name?:string} $recipient */

    public function addTo(array $recipient): self

    {

        $this->addRecipient($this->to, $recipient);



        return $this;

    }



    /** @param array{Email:string,Name?:string} $recipient */

    public function addCc(array $recipient): self

    {

        $this->addRecipient($this->cc, $recipient);



        return $this;

    }



    /** @param array{Email:string,Name?:string} $recipient */

    public function addBcc(array $recipient): self

    {

        $this->addRecipient($this->bcc, $recipient);



        return $this;

    }



    /** @param array{Email:string,Name?:string} $replyTo */

    public function setReplyTo(array $replyTo): self

    {

        $email = sanitize_email((string) ($replyTo['Email'] ?? ''));

        $name = sanitize_text_field((string) ($replyTo['Name'] ?? ''));



        if ($email !== '' && is_email($email)) {

            $this->replyTo = ['Email' => $email, 'Name' => $name];

        }



        return $this;

    }



    public function setSubject(string $subject): self

    {

        $this->subject = trim(wp_strip_all_tags($subject));



        return $this;

    }



    public function setTextPart(string $text): self

    {

        $this->textPart = $text;



        return $this;

    }



    public function setHtmlPart(string $html): self

    {

        $this->htmlPart = $html;



        return $this;

    }



    /** @param array<string,mixed> $variables */

    public function setTemplate(int $templateId, array $variables = [], bool $templateLanguage = true): self

    {

        $this->templateId = max(0, $templateId);

        $this->variables = $variables;

        $this->templateLanguage = $templateLanguage;



        return $this;

    }



    public function addAttachment(string $filename, string $contentType, string $base64Content): self

    {

        $filename = sanitize_file_name($filename);

        $contentType = sanitize_text_field($contentType);



        if ($filename !== '' && $base64Content !== '') {

            $this->attachments[] = [

                'ContentType' => $contentType !== '' ? $contentType : 'application/octet-stream',

                'Filename' => $filename,

                'Base64Content' => $base64Content,

            ];

        }



        return $this;

    }



    public function hasRecipients(): bool

    {

        return $this->to !== [];

    }



    /** @return array{Email:string,Name:string}|null */

    public function from(): ?array

    {

        return $this->from;

    }



    /**

     * Convert to Mailjet Send API v3.1 message payload.

     *

     * @param array{Email:string,Name:string} $defaultFrom

     * @return array<string,mixed>

     */

    public function toMailjetPayload(array $defaultFrom): array

    {

        $message = [

            'From' => $this->from ?? $defaultFrom,

            'To' => $this->to,

            'Subject' => $this->subject,

        ];



        if ($this->cc !== []) {

            $message['Cc'] = $this->cc;

        }



        if ($this->bcc !== []) {

            $message['Bcc'] = $this->bcc;

        }



        if ($this->replyTo !== null) {

            $message['ReplyTo'] = $this->replyTo;

        }



        if ($this->templateId > 0) {

            $message['TemplateID'] = $this->templateId;

            $message['TemplateLanguage'] = $this->templateLanguage;

            $message['Variables'] = $this->variables;

        } else {

            if ($this->textPart !== '') {

                $message['TextPart'] = $this->textPart;

            }



            if ($this->htmlPart !== '') {

                $message['HTMLPart'] = $this->htmlPart;

            }

        }



        if ($this->attachments !== []) {

            $message['Attachments'] = $this->attachments;

        }



        return ['Messages' => [$message]];

    }



    /**

     * @param array<int,array{Email:string,Name:string}> $list

     * @param array{Email:string,Name?:string} $recipient

     */

    private function addRecipient(array &$list, array $recipient): void

    {

        $email = sanitize_email((string) ($recipient['Email'] ?? ''));

        if ($email === '' || !is_email($email)) {

            return;

        }



        $list[] = [

            'Email' => $email,

            'Name' => sanitize_text_field((string) ($recipient['Name'] ?? '')),

        ];

    }

}

