# Email Notification Setup Guide

## Overview
The invoice email notification system has been implemented. When an invoice is generated, an email is automatically sent to the user with invoice details and a link to view/download the invoice.

## Configuration

### Environment Variables
Add these to your `.env` file:

```env
# Email Configuration
SMTP_FROM_EMAIL=noreply@livonto.com
SMTP_FROM_NAME=Livonto
```

### PHP mail() Function
The current implementation uses PHP's built-in `mail()` function. For production environments, you may want to:

1. **Use PHPMailer** (Recommended for production):
   - Install via Composer: `composer require phpmailer/phpmailer`
   - Update `app/email_helper.php` to use PHPMailer instead of `mail()`

2. **Configure SMTP** (if using PHPMailer):
   ```env
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USERNAME=your-email@gmail.com
   SMTP_PASSWORD=your-app-password
   SMTP_ENCRYPTION=tls
   ```

3. **Test Email Configuration**:
   - Check server logs: `storage/logs/`
   - Verify email delivery in spam/junk folders
   - Test with a real email address

## Features

### PDF Download
- Uses `html2pdf.js` library (loaded from CDN)
- Generates PDF exactly as the invoice appears on screen
- Preserves all styling, colors, and formatting
- Downloads automatically with filename: `Invoice-INV-YYYYMMDD-XXXX.pdf`

### Email Notification
- Sent automatically when invoice is created
- Includes invoice number, date, amount, and property details
- Contains a direct link to view the invoice
- HTML formatted email with professional styling

## Testing

1. **Test PDF Download**:
   - Go to any invoice page
   - Click "Download PDF" button
   - Verify PDF is generated correctly

2. **Test Email Notification**:
   - Complete a booking payment
   - Check user's email inbox
   - Verify email contains correct invoice details

## Troubleshooting

### PDF Not Downloading
- Check browser console for errors
- Verify `html2pdf.js` is loading from CDN
- Check internet connection (CDN requires internet)

### Emails Not Sending
- Check PHP `mail()` function is enabled on server
- Verify email addresses are valid
- Check server error logs: `storage/logs/`
- For production, use PHPMailer with SMTP

### Email Going to Spam
- Configure SPF/DKIM records for your domain
- Use a proper "From" email address
- Avoid spam trigger words in subject/body

## Future Enhancements

Consider implementing:
- Email queue system for better reliability
- PDF attachment in email (requires PHPMailer)
- Email templates system
- Email delivery tracking
- Retry mechanism for failed emails

