<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Email Content Formatter
 *
 * Handles conversion and formatting of email content to ensure:
 * - Proper formatting preservation
 * - No content loss
 * - Readable output in both HTML and plain text
 * - Removal of unnecessary email artifacts
 */
class EmailContentFormatter
{
    /**
     * Format email content for ticket display
     * Preserves HTML formatting when available, otherwise uses enhanced plain text
     *
     * @param string|null $bodyHtml HTML version of email body
     * @param string|null $bodyPlain Plain text version of email body
     * @param bool $preferHtml Whether to prefer HTML over plain text
     * @return array ['html' => string|null, 'plain' => string|null, 'display' => string]
     */
    public function formatEmailContent(?string $bodyHtml, ?string $bodyPlain, bool $preferHtml = true): array
    {
        $formattedHtml = null;
        $formattedPlain = null;
        $displayContent = null;

        // Process HTML content if available
        if (!empty($bodyHtml)) {
            $formattedHtml = $this->cleanHtmlContent($bodyHtml);

            // Also generate a plain text version from HTML for fallback
            $plainFromHtml = $this->convertHtmlToFormattedText($bodyHtml);

            if ($preferHtml) {
                $displayContent = $formattedHtml;
            } else {
                $displayContent = $plainFromHtml;
                $formattedPlain = $plainFromHtml;
            }
        }

        // Process plain text content if available
        if (!empty($bodyPlain)) {
            $formattedPlain = $this->cleanPlainTextContent($bodyPlain);

            // Use plain text as display if no HTML or if plain is preferred
            if (empty($displayContent) || !$preferHtml) {
                $displayContent = $formattedPlain;
            }
        }

        // Final fallback
        if (empty($displayContent)) {
            $displayContent = '[No readable content available]';
            Log::warning('Email content formatting resulted in empty display content', [
                'has_html' => !empty($bodyHtml),
                'has_plain' => !empty($bodyPlain),
            ]);
        }

        return [
            'html' => $formattedHtml,
            'plain' => $formattedPlain,
            'display' => $displayContent,
        ];
    }

    /**
     * Clean HTML content while preserving formatting
     *
     * @param string $html Raw HTML content
     * @return string Cleaned HTML
     */
    protected function cleanHtmlContent(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Ensure UTF-8 encoding
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'auto');
        }

        // REMOVE QUOTED EMAIL HISTORY - this must come FIRST before other processing
        $html = $this->stripQuotedEmailContent($html);

        // Remove script and style tags with their content
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/isu', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/isu', '', $html);

        // Remove common email tracking pixels and beacons
        $html = preg_replace('/<img[^>]+src=["\'][^"\']*tracking[^"\']*["\'][^>]*>/isu', '', $html);
        $html = preg_replace('/<img[^>]+width=["\']1["\'][^>]+height=["\']1["\'][^>]*>/isu', '', $html);

        // Clean up Microsoft Office HTML artifacts
        $html = preg_replace('/<o:p>.*?<\/o:p>/isu', '', $html);
        $html = preg_replace('/<!--\[if.*?\]>.*?<!\[endif\]-->/isu', '', $html);

        // Remove inline styles that might break display (keep the elements, just remove style attributes)
        // This is more conservative - we keep structure but normalize styling
        $html = preg_replace('/\sstyle=["\'][^"\']*["\']/iu', '', $html);

        // Remove excessive whitespace between tags
        $html = preg_replace('/>\s+</u', '><', $html);

        // Clean up excessive line breaks while preserving paragraph structure
        $html = preg_replace('/(<br\s*\/?>\s*){3,}/iu', '<br><br>', $html);

        // Decode HTML entities to make text readable
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($html);
    }

    /**
     * Strip quoted email content from HTML
     * Removes email history like "On [date] ... wrote:" and everything after
     *
     * @param string $html HTML content
     * @return string HTML with quoted content removed
     */
    protected function stripQuotedEmailContent(string $html): string
    {
        // Remove Gmail-style quoted blocks (class="gmail_quote")
        $html = preg_replace('/<div[^>]*class=["\'][^"\']*gmail_quote[^"\']*["\'][^>]*>.*?<\/div>/isu', '', $html);
        $html = preg_replace('/<blockquote[^>]*class=["\'][^"\']*gmail_quote[^"\']*["\'][^>]*>.*?<\/blockquote>/isu', '', $html);

        // Remove any blockquote sections (usually quoted previous emails)
        $html = preg_replace('/<blockquote[^>]*>.*?<\/blockquote>/isu', '', $html);

        // Remove content starting with "On [date] ... wrote:" pattern (Gmail, Outlook, etc.)
        // This pattern matches various date formats and languages
        $html = preg_replace('/On\s+.+?wrote:\s*<\/?.+?>.*$/isu', '', $html);
        $html = preg_replace('/On\s+.+?wrote:.*$/isu', '', $html);

        // Remove "From:", "Sent:", "To:", "Subject:" email headers (forwarded/replied emails)
        $html = preg_replace('/<div[^>]*>\s*(From|Sent|To|Subject|Date):.*?<\/div>/isu', '', $html);
        $html = preg_replace('/<p[^>]*>\s*(From|Sent|To|Subject|Date):.*?<\/p>/isu', '', $html);

        // Remove content after horizontal rules (often separates original message)
        $html = preg_replace('/<hr[^>]*>.*$/isu', '', $html);

        return $html;
    }

    /**
     * Convert HTML to well-formatted plain text that preserves structure
     *
     * @param string $html HTML content
     * @return string Formatted plain text
     */
    protected function convertHtmlToFormattedText(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Ensure UTF-8 encoding
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'auto');
        }

        // Remove script and style tags with their content
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/isu', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/isu', '', $html);

        // Convert headers to text with newlines
        $html = preg_replace('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/isu', "\n\n## $1 ##\n\n", $html);

        // Convert paragraphs to double newlines
        $html = preg_replace('/<p[^>]*>(.*?)<\/p>/isu', "$1\n\n", $html);

        // Convert divs to newlines
        $html = preg_replace('/<div[^>]*>(.*?)<\/div>/isu', "$1\n", $html);

        // Convert line breaks to newlines
        $html = preg_replace('/<br\s*\/?>/iu', "\n", $html);

        // Convert horizontal rules
        $html = preg_replace('/<hr\s*\/?>/iu', "\n" . str_repeat('-', 50) . "\n", $html);

        // Convert blockquotes with indentation
        $html = preg_replace_callback('/<blockquote[^>]*>(.*?)<\/blockquote>/isu', function($matches) {
            $content = trim($matches[1]);
            $lines = explode("\n", $content);
            $quoted = array_map(function($line) {
                return '> ' . $line;
            }, $lines);
            return "\n" . implode("\n", $quoted) . "\n";
        }, $html);

        // Convert unordered lists
        $html = preg_replace_callback('/<ul[^>]*>(.*?)<\/ul>/isu', function($matches) {
            $content = $matches[1];
            $content = preg_replace('/<li[^>]*>(.*?)<\/li>/isu', "• $1\n", $content);
            return "\n" . $content . "\n";
        }, $html);

        // Convert ordered lists
        $html = preg_replace_callback('/<ol[^>]*>(.*?)<\/ol>/isu', function($matches) {
            $content = $matches[1];
            $counter = 1;
            $content = preg_replace_callback('/<li[^>]*>(.*?)<\/li>/isu', function($m) use (&$counter) {
                return ($counter++) . ". " . $m[1] . "\n";
            }, $content);
            return "\n" . $content . "\n";
        }, $html);

        // Convert strong/bold to uppercase or asterisks
        $html = preg_replace('/<(strong|b)[^>]*>(.*?)<\/\1>/isu', '**$2**', $html);

        // Convert emphasis/italic to underscores
        $html = preg_replace('/<(em|i)[^>]*>(.*?)<\/\1>/isu', '_$2_', $html);

        // Convert links but preserve URL
        $html = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/isu', '$2 [$1]', $html);

        // Convert code blocks
        $html = preg_replace('/<pre[^>]*>(.*?)<\/pre>/isu', "\n```\n$1\n```\n", $html);
        $html = preg_replace('/<code[^>]*>(.*?)<\/code>/isu', '`$1`', $html);

        // Convert tables to text format (basic)
        $html = preg_replace('/<table[^>]*>/isu', "\n", $html);
        $html = preg_replace('/<\/table>/isu', "\n", $html);
        $html = preg_replace('/<tr[^>]*>/isu', "", $html);
        $html = preg_replace('/<\/tr>/isu', "\n", $html);
        $html = preg_replace('/<(td|th)[^>]*>/isu', "  ", $html);
        $html = preg_replace('/<\/(td|th)>/isu', " | ", $html);

        // Remove all remaining HTML tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean up the text
        $text = $this->cleanPlainTextContent($text);

        return $text;
    }

    /**
     * Clean plain text content
     *
     * @param string $text Plain text content
     * @return string Cleaned plain text
     */
    protected function cleanPlainTextContent(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Ensure UTF-8 encoding
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        // FIRST: Remove quoted sections using aggressive pattern matching
        $text = $this->stripQuotedPlainTextContent($text);

        // Split into lines for processing
        $lines = explode("\n", $text);
        $cleanedLines = [];
        $signatureFound = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Stop at common email signatures (multilingual support)
            if ($this->isSignatureLine($trimmedLine)) {
                $signatureFound = true;
                break;
            }

            // Stop at quoted reply patterns
            if ($this->isQuotedReplyStart($trimmedLine)) {
                break;
            }

            // Skip lines starting with > (quoted text)
            if (preg_match('/^>+/', $trimmedLine)) {
                continue;
            }

            // Skip common email disclaimers
            if ($this->isDisclaimerLine($trimmedLine)) {
                break;
            }

            // Skip "Sent from my iPhone/Android" etc
            if (preg_match('/^Sent from my (iPhone|iPad|Android|Samsung|Mobile|Huawei|BlackBerry|Windows Phone)/iu', $trimmedLine)) {
                break;
            }

            // Skip email client footers
            if (preg_match('/^(Get Outlook|Download|Virus-free|Antivirus|www\.|http)/iu', $trimmedLine)) {
                break;
            }

            // Skip empty lines if we haven't found content yet
            if (empty($cleanedLines) && empty($trimmedLine)) {
                continue;
            }

            $cleanedLines[] = $line;
        }

        // Join lines back together
        $cleaned = implode("\n", $cleanedLines);

        // Remove excessive whitespace - LIMIT TO MAX 2 LINE BREAKS between paragraphs
        $cleaned = preg_replace('/\n{3,}/u', "\n\n", $cleaned);

        // Remove excessive spaces (but preserve single spaces)
        $cleaned = preg_replace('/[ \t]{2,}/u', ' ', $cleaned);

        // Remove trailing spaces from each line
        $cleaned = preg_replace('/ +$/mu', '', $cleaned);

        // Remove any remaining control characters except newlines and tabs
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleaned);

        // Trim
        $cleaned = trim($cleaned);

        return $cleaned;
    }

    /**
     * Strip quoted email content from plain text
     * Removes "On [date] ... wrote:" and everything after it
     *
     * @param string $text Plain text content
     * @return string Text with quoted content removed
     */
    protected function stripQuotedPlainTextContent(string $text): string
    {
        // Pattern to match "On [date/time] [name/email] wrote:" and everything after
        // This covers Gmail, Outlook, and most email clients
        $patterns = [
            '/On\s+.+?\s+at\s+.+?wrote:.*$/isu',  // "On [date] at [time] [name] wrote:"
            '/On\s+.+?,\s+.+?wrote:.*$/isu',      // "On [date], [name] wrote:"
            '/On\s+.+?wrote:.*$/isu',              // "On [date] [name] wrote:"
            '/\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2}.*?wrote:.*$/isu',  // Date format at start
            '/From:.*?Subject:.*$/isu',            // Email headers block
            '/-{3,}\s*Original Message\s*-{3,}.*$/isu',  // Outlook-style separators
            '/_____+.*$/isu',                      // Separator lines
            '/={3,}.*$/isu',                       // Another common separator
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }

        return $text;
    }

    /**
     * Check if a line is a signature line
     *
     * @param string $line Trimmed line of text
     * @return bool
     */
    protected function isSignatureLine(string $line): bool
    {
        // Common signature delimiters
        if (preg_match('/^(--|__|-_|_-)\s*$/u', $line)) {
            return true;
        }

        // Common signature phrases in multiple languages
        $signaturePhrases = [
            // English
            'Best regards', 'Kind regards', 'Regards', 'Sincerely', 'Cheers', 'Best', 'Thanks', 'Thank you',
            'Yours truly', 'Yours sincerely', 'Warm regards', 'With gratitude', 'Respectfully',
            // Spanish
            'Saludos', 'Cordialmente', 'Atentamente', 'Un saludo', 'Gracias',
            // French
            'Cordialement', 'Bien cordialement', 'Salutations', 'Amicalement', 'Merci',
            // German
            'Mit freundlichen Grüßen', 'Freundliche Grüße', 'Viele Grüße', 'Beste Grüße', 'Danke',
            // Italian
            'Cordiali saluti', 'Distinti saluti', 'Grazie',
            // Portuguese
            'Com os melhores cumprimentos', 'Atenciosamente', 'Cordialmente', 'Obrigado',
            // Russian
            'С уважением', 'Всего доброго', 'Спасибо',
            // Chinese
            '此致', '敬礼', '谢谢',
            // Japanese
            '敬具', 'よろしくお願いします',
            // Arabic
            'مع تحياتي', 'مع خالص التقدير',
            // Dutch
            'Met vriendelijke groet', 'Groeten',
            // Polish
            'Pozdrawiam', 'Z poważaniem',
            // Swedish
            'Med vänlig hälsning', 'Hälsningar',
            // Danish
            'Med venlig hilsen', 'Hilsner',
            // Norwegian
            'Med vennlig hilsen', 'Hilsen',
            // Finnish
            'Ystävällisin terveisin', 'Terveisin',
        ];

        foreach ($signaturePhrases as $phrase) {
            if (preg_match('/^' . preg_quote($phrase, '/') . ',?\s*$/ui', $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a line is the start of a quoted reply
     *
     * @param string $line Trimmed line of text
     * @return bool
     */
    protected function isQuotedReplyStart(string $line): bool
    {
        // "On [date] [person] wrote:" patterns in multiple languages
        $patterns = [
            '/^(On|El|Le|Am|Il|Em|Den|Op|在|على|Dne|W dniu|Den|日付) .+ (wrote|escribió|a écrit|schrieb|ha scritto|escreveu|skrev|schreef|napsal|napisał|написал|写道|كتب):$/ui',
            '/^[0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{2,4}.+(wrote|escribió|a écrit|schrieb|ha scritto|escreveu|skrev|napsal|написал):$/ui',
            '/^\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2}.+(wrote|escribió|a écrit|schrieb|ha scritto|escreveu|skrev|napsal|написал):$/ui',
            '/^From:.+$/ui', // Email client forward/reply headers
            '/^Sent:.+$/ui',
            '/^To:.+$/ui',
            '/^Subject:.+$/ui',
            '/^Date:.+$/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a line is a disclaimer line
     *
     * @param string $line Trimmed line of text
     * @return bool
     */
    protected function isDisclaimerLine(string $line): bool
    {
        $disclaimerPatterns = [
            '/^(CONFIDENTIAL|DISCLAIMER|NOTICE|WARNING|CAUTION|IMPORTANT)/ui',
            '/^This (email|message|communication)/ui',
            '/^The information contained/ui',
            '/^If you (are not|have received this)/ui',
            '/^This transmission/ui',
            '/^Please consider the environment/ui',
            '/^No trees were killed/ui',
        ];

        foreach ($disclaimerPatterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract plain text preview from content (for listings/previews)
     *
     * @param string|null $bodyHtml HTML content
     * @param string|null $bodyPlain Plain text content
     * @param int $maxLength Maximum length of preview
     * @return string Preview text
     */
    public function extractPreview(?string $bodyHtml, ?string $bodyPlain, int $maxLength = 200): string
    {
        $content = null;

        // Prefer plain text for preview
        if (!empty($bodyPlain)) {
            $content = $this->cleanPlainTextContent($bodyPlain);
        } elseif (!empty($bodyHtml)) {
            $content = $this->convertHtmlToFormattedText($bodyHtml);
        }

        if (empty($content)) {
            return '[No content]';
        }

        // Remove newlines for preview
        $content = str_replace(["\r", "\n"], ' ', $content);

        // Remove excessive spaces
        $content = preg_replace('/\s+/', ' ', $content);

        // Trim to max length
        if (mb_strlen($content) > $maxLength) {
            $content = mb_substr($content, 0, $maxLength) . '...';
        }

        return trim($content);
    }

    /**
     * Sanitize HTML for safe display (XSS prevention)
     *
     * @param string $html HTML content
     * @return string Sanitized HTML
     */
    public function sanitizeHtml(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Remove potentially dangerous tags
        $dangerousTags = ['script', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select'];

        foreach ($dangerousTags as $tag) {
            $html = preg_replace('/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '>/isu', '', $html);
            $html = preg_replace('/<' . $tag . '\b[^>]*\/?>/isu', '', $html);
        }

        // Remove event handlers
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/iu', '', $html);
        $html = preg_replace('/\s*on\w+\s*=\s*[^\s>]*/iu', '', $html);

        // Remove javascript: URLs
        $html = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/iu', '', $html);
        $html = preg_replace('/src\s*=\s*["\']javascript:[^"\']*["\']/iu', '', $html);

        return $html;
    }
}
