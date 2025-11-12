<?php
/**
 * Terms and Conditions Page
 */

$pageTitle = "Terms and Conditions";
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';
require __DIR__ . '/../app/includes/header.php';
?>

<style>
.terms-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 0;
}

.terms-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
    color: white;
    padding: 3rem 2rem;
    border-radius: var(--card-radius);
    margin-bottom: 2rem;
    text-align: center;
}

.terms-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: white;
}

.terms-header .subtitle {
    font-size: 1rem;
    opacity: 0.95;
}

.terms-content {
    background: var(--card-bg);
    border-radius: var(--card-radius);
    padding: 3rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-1);
    line-height: 1.8;
}

.terms-content h2 {
    color: var(--primary-700);
    font-size: 1.5rem;
    font-weight: 600;
    margin-top: 2.5rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary);
}

.terms-content h2:first-of-type {
    margin-top: 0;
}

.terms-content h3 {
    color: var(--primary);
    font-size: 1.2rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.terms-content p {
    margin-bottom: 1rem;
    color: var(--text);
}

.terms-content ul, .terms-content ol {
    margin-bottom: 1rem;
    padding-left: 2rem;
}

.terms-content li {
    margin-bottom: 0.5rem;
    color: var(--text);
}

.terms-content strong {
    color: var(--primary-700);
    font-weight: 600;
}

.terms-content .highlight {
    background: rgba(139, 107, 209, 0.1);
    padding: 1rem;
    border-left: 4px solid var(--primary);
    margin: 1.5rem 0;
    border-radius: 4px;
}

.terms-content .contact-info {
    background: var(--bg);
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 1.5rem;
}

.terms-content .contact-info strong {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--primary-700);
}

@media (max-width: 768px) {
    .terms-content {
        padding: 2rem 1.5rem;
    }
    
    .terms-header {
        padding: 2rem 1.5rem;
    }
    
    .terms-header h1 {
        font-size: 2rem;
    }
}
</style>

<div class="terms-container">
    <div class="terms-header">
        <h1><i class="bi bi-file-text me-2"></i>Terms and Conditions</h1>
        <p class="subtitle">Last Updated: June 10, 2025</p>
    </div>

    <div class="terms-content">
        <div class="highlight">
            <p><strong>Electronic Record:</strong> This document is an electronic record under the Information Technology Act, 2000 and the rules framed thereunder, and is published in accordance with Rule 3(1) of the Information Technology (Intermediary Guidelines and Digital Media Ethics Code) Rules, 2021. It does not require any physical or digital signature.</p>
        </div>

        <p>This Terms and Conditions document ("Agreement") is effective as of <strong>10th day of June, 2025</strong> and constitutes a legally binding agreement between the User ("you"/"your") and <strong>Livonto</strong> ("we"/"our"/"us"), a digital platform operated by [Insert Legal Entity Name], having its registered office at <strong>8/1A Sir William Jones, Sarani, Middleton Row, Kolkata, West Bengal, India, 700071</strong>.</p>

        <h2>A. Definitions</h2>
        <ul>
            <li><strong>"Platform"</strong> refers to the website (https://livonto.in) and associated mobile applications and digital services operated by Livonto.</li>
            <li><strong>"Services"</strong> means the facilitation of digital listings, promotion, coordination, and student onboarding into Paying Guest (PG) accommodations owned and managed by third-party operators.</li>
            <li><strong>"Accommodation Provider"</strong> means any third-party individual or entity who owns, operates or manages PG accommodations listed on the Platform.</li>
            <li><strong>"User"</strong> means any individual, parent, guardian, or entity accessing or availing the Services through the Platform.</li>
        </ul>

        <h2>B. Acceptance and Eligibility</h2>
        <p>By using or accessing the Platform, you confirm that you have read, understood and agreed to be bound by this Agreement. If you are under the age of 18, you must access or use the Services only under the supervision of a parent or legal guardian, who shall be deemed to have accepted these Terms on your behalf.</p>

        <h2>C. Nature of Services</h2>
        <p>Livonto is an online intermediary that facilitates discovery and coordination of PG accommodations. We do not own, lease, license or operate any listed property. Listings are made available by Accommodation Providers, who are solely responsible for the accuracy of information, availability, legal compliance, and the condition of the property. Livonto does not provide brokerage, real estate agency, tenancy or property management services.</p>

        <h2>D. User Obligations</h2>
        <ul>
            <li>Users shall provide complete, true, and accurate information at the time of registration and shall keep such information updated.</li>
            <li>Users are responsible for maintaining confidentiality of their login credentials and for all activities carried out through their account.</li>
            <li>Users agree not to:
                <ul>
                    <li>Use the Platform for unlawful or unauthorised purposes;</li>
                    <li>Post false, misleading, or defamatory content;</li>
                    <li>Circumvent any payment obligations;</li>
                    <li>Attempt to harm or disrupt the integrity or functionality of the Platform.</li>
                </ul>
            </li>
        </ul>

        <h2>E. Bookings and Financial Terms</h2>
        <ul>
            <li>The booking process is initiated via the Platform but is subject to acceptance and final confirmation by the Accommodation Provider.</li>
            <li>Users shall make payments, including security deposits, as per the agreed terms with the respective Accommodation Provider.</li>
            <li>Livonto is not a party to any rental or licence agreement and shall not be responsible for the actions or defaults of either party.</li>
            <li>Applicable taxes, including Goods and Services Tax (GST), shall be borne by the User in accordance with law.</li>
        </ul>

        <h2>F. Cancellations, Refunds and Exit</h2>
        <ul>
            <li>Cancellations, refunds and exit formalities are governed by the cancellation and refund policies of the respective Accommodation Provider.</li>
            <li>Livonto does not hold or disburse payments on behalf of any party unless explicitly agreed in writing.</li>
            <li>Users are advised to review specific accommodation policies before making payments or entering into any occupancy arrangement.</li>
        </ul>

        <h2>G. Limitation of Liability</h2>
        <p>To the fullest extent permitted by law, Livonto shall not be liable for:</p>
        <ul>
            <li>Any direct or indirect loss or damages arising from the actions or omissions of Accommodation Providers;</li>
            <li>Discrepancies in property description or availability;</li>
            <li>Any temporary or permanent disruption of Platform Services;</li>
            <li>Any disputes between the User and the Accommodation Provider.</li>
        </ul>

        <h2>H. User Content and Feedback</h2>
        <ul>
            <li>Users may voluntarily post reviews, feedback, and content about their experience on the Platform.</li>
            <li>Livonto reserves the right to remove, moderate or restrict access to such content without notice.</li>
            <li>By submitting content, the User grants Livonto a non-exclusive, worldwide, royalty-free license to use such content for lawful purposes.</li>
        </ul>

        <h2>I. Data Privacy and Security</h2>
        <p>All personal data collected shall be processed in accordance with applicable data protection laws in India. Please refer to our Privacy Policy for a detailed explanation of how data is collected, processed, stored and shared.</p>

        <h2>J. Modifications to Terms</h2>
        <p>Livonto reserves the right to modify these Terms, the Services or features of the Platform at any time. Users are encouraged to check the Terms periodically. Continued usage of the Platform shall constitute deemed acceptance of the amended Terms.</p>

        <h2>K. Suspension and Termination</h2>
        <p>Livonto may suspend, restrict or terminate access to the Platform at its sole discretion in the event of any breach of these Terms or misuse of the Services.</p>

        <h2>L. Intellectual Property Rights</h2>
        <p>All content, trademarks, logos, icons, service names and other intellectual property appearing on the Platform are the exclusive property of Livonto. No User is permitted to use or reproduce any proprietary material without prior written consent.</p>

        <h2>M. Governing Law and Dispute Resolution</h2>
        <p>These Terms shall be governed by and construed in accordance with the laws of India. Any dispute arising under or in connection with these Terms shall be subject to the exclusive jurisdiction of the courts located in <strong>Kolkata, India</strong>.</p>

        <h2>N. Grievance Redressal Mechanism</h2>
        <p>In accordance with Rule 3(2) of the IT Rules 2021, the grievance redressal contact is provided below:</p>
        
        <div class="contact-info">
            <strong>Grievance Officer:</strong> Mr. Mayank Saraf<br><br>
            <strong>Email:</strong> <a href="mailto:livontopg@gmail.com">livontopg@gmail.com</a><br><br>
            <strong>Address:</strong> 8/1A Sir William Jones, Sarani, Middleton Row, Kolkata, West Bengal, India, 700071
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>

