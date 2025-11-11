# Email Setup Guide

## Overview
The email system uses PHPMailer with SMTP support for reliable email delivery. It automatically falls back to PHP's `mail()` function if SMTP is not configured.

## Installation

1. **Install PHPMailer via Composer**:
   ```bash
   composer install
   ```
   This will install PHPMailer along with other dependencies.

## Configuration

### Environment Variables
Add these to your `.env` file:

#### Basic Email Configuration (Required)
```env
# Email Configuration
SMTP_FROM_EMAIL=noreply@livonto.com
SMTP_FROM_NAME=Livonto
```

#### SMTP Configuration (Optional - for production)
If SMTP is configured, PHPMailer will be used. Otherwise, it falls back to PHP `mail()`.

```env
# SMTP Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_ENCRYPTION=tls
```

### Common SMTP Providers

#### Gmail
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_ENCRYPTION=tls
```
**Note:** For Gmail, you need to use an [App Password](https://support.google.com/accounts/answer/185833) instead of your regular password.

#### Outlook/Hotmail
```env
SMTP_HOST=smtp-mail.outlook.com
SMTP_PORT=587
SMTP_USERNAME=your-email@outlook.com
SMTP_PASSWORD=your-password
SMTP_ENCRYPTION=tls
```

#### Custom SMTP Server (Your Server Mail)
```env
# Example for a custom mail server on your domain
SMTP_HOST=mail.yourdomain.com
SMTP_PORT=587
SMTP_USERNAME=noreply@yourdomain.com
SMTP_PASSWORD=your-password
SMTP_ENCRYPTION=tls

# OR if your server uses SSL instead of TLS
SMTP_HOST=mail.yourdomain.com
SMTP_PORT=465
SMTP_USERNAME=noreply@yourdomain.com
SMTP_PASSWORD=your-password
SMTP_ENCRYPTION=ssl
```

**Note:** Common ports:
- **587** - TLS (STARTTLS)
- **465** - SSL
- **25** - Usually blocked by ISPs, not recommended

## Features

### Automatic Method Selection
- **SMTP (PHPMailer)**: Used if SMTP credentials are configured
- **PHP mail()**: Fallback if SMTP is not configured

### Email Types

1. **Invoice Emails**
   - Sent automatically when invoice is created
   - Includes invoice details
   - Users can download invoice PDF from their profile page

2. **General Emails**
   - Support for HTML emails
   - Attachment support (when using SMTP)
   - UTF-8 character encoding

## Testing

### Test Email Configuration

1. **Check if PHPMailer is installed**:
   ```bash
   composer show phpmailer/phpmailer
   ```

2. **Enable debug mode** (in `.env`):
   ```env
   APP_DEBUG=true
   ```
   This will show detailed SMTP debug information in error logs.

3. **Check error logs**:
   - Server error logs
   - Application logs: `storage/logs/` (if configured)

4. **Test email sending**:
   - Complete a booking to trigger invoice email
   - Check recipient's inbox (and spam folder)
   - Verify email contains correct information

## Troubleshooting

### Emails Not Sending

1. **Check SMTP Configuration**:
   - Verify all SMTP variables are set correctly
   - Test SMTP credentials with an email client
   - Check if SMTP port is not blocked by firewall

2. **Check PHP mail() Function** (if using fallback):
   - Verify `mail()` function is enabled in PHP
   - Check server mail configuration
   - Review server error logs

3. **Check Error Logs**:
   - PHP error log
   - Application error log
   - SMTP debug output (if `APP_DEBUG=true`)

### Email Going to Spam

1. **Configure SPF Record**:
   - Add SPF record to your domain DNS
   - Example: `v=spf1 include:_spf.google.com ~all`

2. **Configure DKIM**:
   - Set up DKIM signing for your domain
   - This helps with email authentication

3. **Use Proper From Address**:
   - Use an email address from your domain
   - Avoid generic addresses like `noreply@`

4. **Email Content**:
   - Avoid spam trigger words
   - Include proper HTML structure
   - Add unsubscribe link if sending marketing emails

### Common SMTP Errors

1. **"SMTP connect() failed"**:
   - Check SMTP host and port
   - Verify firewall allows SMTP connections
   - Check if SSL/TLS is required

2. **"Authentication failed"**:
   - Verify username and password
   - For Gmail, use App Password, not regular password
   - Check if account has 2FA enabled (requires App Password)

3. **"Connection timeout"**:
   - Check network connectivity
   - Verify SMTP host is correct
   - Check if port is blocked

## Advanced Features

### Invoice Email
To send invoice email notification:
```php
sendInvoiceEmail($invoiceId, $email, $name);
```

**Note:** Invoice PDFs are not attached to emails. Users can download their invoice PDF from their profile page.

### Custom Email Templates
You can create custom email templates by modifying the HTML in `sendInvoiceEmail()` function or creating new functions in `email_helper.php`.

## Security Notes

1. **Never commit `.env` file** to version control
2. **Use App Passwords** for Gmail instead of regular passwords
3. **Keep SMTP credentials secure**
4. **Use TLS/SSL encryption** for SMTP connections
5. **Validate email addresses** before sending

## Production Recommendations

1. **Use SMTP** instead of PHP `mail()` for better deliverability
2. **Set up SPF/DKIM records** for your domain
3. **Monitor email delivery rates**
4. **Implement email queue** for high-volume sending
5. **Use dedicated email service** (SendGrid, Mailgun, etc.) for better reliability
