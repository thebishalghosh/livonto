/**
 * Invoice PDF Download Script
 * Uses html2pdf.js to generate PDF from invoice HTML exactly as displayed
 */

// Load html2pdf.js library dynamically if not already loaded
function loadHtml2Pdf() {
    return new Promise((resolve, reject) => {
        if (window.html2pdf) {
            resolve();
            return;
        }
        
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load html2pdf.js'));
        document.head.appendChild(script);
    });
}

/**
 * Download invoice as PDF
 * Preserves exact appearance of the invoice page
 */
async function downloadInvoicePDF() {
    try {
        // Show loading indicator
        const downloadBtn = document.getElementById('downloadPdfBtn');
        const originalText = downloadBtn ? downloadBtn.innerHTML : '';
        if (downloadBtn) {
            downloadBtn.disabled = true;
            downloadBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating PDF...';
        }
        
        // Load html2pdf library
        await loadHtml2Pdf();
        
        // Get the invoice container
        const invoiceContainer = document.querySelector('.invoice-container');
        if (!invoiceContainer) {
            throw new Error('Invoice container not found');
        }
        
        // Clone the container to avoid modifying the original
        const element = invoiceContainer.cloneNode(true);
        
        // Remove action buttons from cloned element
        const actions = element.querySelector('.actions');
        if (actions) {
            actions.remove();
        }
        
        // Ensure images are loaded before generating PDF
        const images = element.querySelectorAll('img');
        const imagePromises = Array.from(images).map(img => {
            return new Promise((resolve) => {
                if (img.complete) {
                    resolve();
                } else {
                    img.onload = resolve;
                    img.onerror = resolve; // Continue even if image fails
                }
            });
        });
        
        await Promise.all(imagePromises);
        
        // Configure PDF options
        const opt = {
            margin: [0, 0, 0, 0],
            filename: 'Invoice-' + (document.querySelector('.invoice-info h1')?.nextElementSibling?.textContent?.match(/INV-[0-9-]+/)?.[0] || 'Invoice') + '.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2,
                useCORS: true,
                logging: false,
                letterRendering: true,
                allowTaint: false
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait',
                compress: true
            },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };
        
        // Generate and download PDF
        await html2pdf().set(opt).from(element).save();
        
        // Restore button
        if (downloadBtn) {
            downloadBtn.disabled = false;
            downloadBtn.innerHTML = originalText;
        }
        
    } catch (error) {
        alert('Failed to generate PDF: ' + error.message);
        
        // Restore button
        const downloadBtn = document.getElementById('downloadPdfBtn');
        if (downloadBtn) {
            downloadBtn.disabled = false;
            downloadBtn.innerHTML = '<i class="bi bi-download"></i> Download PDF';
        }
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Replace the download link with our PDF download function
    const downloadLink = document.querySelector('a[href*="download=1"]');
    if (downloadLink) {
        downloadLink.id = 'downloadPdfBtn';
        downloadLink.href = 'javascript:void(0)';
        downloadLink.onclick = function(e) {
            e.preventDefault();
            downloadInvoicePDF();
        };
    }
    
    // Also handle button with id downloadPdfBtn if it exists
    const downloadBtn = document.getElementById('downloadPdfBtn');
    if (downloadBtn && !downloadBtn.onclick) {
        downloadBtn.onclick = function(e) {
            e.preventDefault();
            downloadInvoicePDF();
        };
    }
});

// Export function for manual use
window.downloadInvoicePDF = downloadInvoicePDF;

