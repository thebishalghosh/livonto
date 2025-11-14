/**
 * Reviews Management JavaScript
 * Handles AJAX operations for reviews: add, edit, delete, and fetch
 */

(function() {
    'use strict';
    
    // Get base URL from global variable or construct it
    const baseUrl = window.appBaseUrl || '';
    const reviewsApiUrl = baseUrl + (baseUrl.endsWith('/') ? '' : '/') + 'reviews-api';
    
    /**
     * Get listing ID from URL or data attribute
     */
    function getListingId() {
        const listingIdEl = document.querySelector('[data-listing-id]');
        if (listingIdEl) {
            return listingIdEl.getAttribute('data-listing-id');
        }
        
        // Try to extract from URL (listings/7)
        const match = window.location.pathname.match(/listings\/(\d+)/);
        if (match) {
            return match[1];
        }
        
        return null;
    }
    
    /**
     * Get current user ID from data attribute
     */
    function getCurrentUserId() {
        const userIdEl = document.querySelector('[data-current-user-id]');
        const userId = userIdEl ? userIdEl.getAttribute('data-current-user-id') : null;
        return userId && userId !== '' && userId !== '0' ? userId : null;
    }
    
    /**
     * Format date for display
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return months[date.getMonth()] + ' ' + date.getDate() + ', ' + date.getFullYear();
    }
    
    /**
     * Get profile image HTML
     */
    function getProfileImageHtml(profileImage, userName) {
        if (profileImage && profileImage.trim() !== '') {
            const imgUrl = profileImage.startsWith('http') ? profileImage : (baseUrl + '/' + profileImage.replace(/^\//, ''));
            return `<img src="${escapeHtml(imgUrl)}" 
                        class="rounded-circle review-profile-img"
                        alt="${escapeHtml(userName)}"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">`;
        }
        return '';
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Convert newlines to <br> tags
     */
    function nl2br(text) {
        return escapeHtml(text).replace(/\n/g, '<br>');
    }
    
    /**
     * Render star rating HTML
     */
    function renderStars(rating) {
        let html = '';
        for (let i = 1; i <= 5; i++) {
            const filled = i <= rating ? '-fill text-warning' : '';
            html += `<i class="bi bi-star${filled}"></i>`;
        }
        return html;
    }
    
    /**
     * Render a single review
     */
    function renderReview(review, currentUserId) {
        const isOwner = review.is_owner || (currentUserId && review.user_id == currentUserId);
        const profileImageHtml = getProfileImageHtml(review.profile_image, review.user_name);
        const userName = review.user_name || 'Anonymous';
        const comment = review.comment ? nl2br(review.comment) : '';
        
        let editDeleteButtons = '';
        if (isOwner) {
            editDeleteButtons = `
                <div class="review-actions ms-auto">
                    <button type="button" class="btn btn-sm btn-outline-primary edit-review-btn" data-review-id="${review.id}">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger delete-review-btn" data-review-id="${review.id}">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            `;
        }
        
        return `
            <div class="review-item border-bottom pb-4 mb-4 theme-border-color" data-review-id="${review.id}">
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        ${profileImageHtml}
                        <i class="bi bi-person-circle review-profile-icon" ${profileImageHtml ? 'style="display: none;"' : ''}></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold mb-1">${escapeHtml(userName)}</div>
                        <div class="small text-muted">
                            ${renderStars(review.rating)}
                            <span class="ms-2">${formatDate(review.created_at)}</span>
                        </div>
                    </div>
                    ${editDeleteButtons}
                </div>
                ${comment ? `<p class="mb-0 text-muted text-line-height-md">${comment}</p>` : ''}
            </div>
        `;
    }
    
    // Pagination state
    let currentPage = 1;
    let totalReviews = 0;
    let hasMore = false;
    let isLoading = false;
    
    /**
     * Load and display reviews
     */
    function loadReviews(page = 1, append = false) {
        const listingId = getListingId();
        if (!listingId) {
            console.error('Listing ID not found');
            return;
        }
        
        const reviewsContainer = document.getElementById('reviews-container');
        if (!reviewsContainer) {
            return;
        }
        
        // Prevent multiple simultaneous requests
        if (isLoading) {
            return;
        }
        isLoading = true;
        
        // Show loading state (only if not appending)
        if (!append) {
            reviewsContainer.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        }
        
        // Show load more button loading state
        const loadMoreBtn = document.getElementById('load-more-reviews-btn');
        if (loadMoreBtn && append) {
            loadMoreBtn.disabled = true;
            loadMoreBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
        }
        
        // Fetch reviews with pagination
        fetch(`${reviewsApiUrl}?listing_id=${listingId}&page=${page}&per_page=10`)
            .then(response => response.json())
            .then(data => {
                isLoading = false;
                
                if (data.status === 'success') {
                    const reviews = data.data.reviews || [];
                    const currentUserId = data.data.current_user_id || getCurrentUserId();
                    const pagination = data.data.pagination || {};
                    
                    // Update pagination state
                    currentPage = pagination.current_page || page;
                    totalReviews = pagination.total_reviews || 0;
                    hasMore = pagination.has_more || false;
                    
                    if (reviews.length === 0 && !append) {
                        reviewsContainer.innerHTML = '<div class="text-center py-4 text-muted"><p>No reviews yet. Be the first to review!</p></div>';
                    } else {
                        let html = '';
                        reviews.forEach(review => {
                            html += renderReview(review, currentUserId);
                        });
                        
                        if (append) {
                            // Append new reviews
                            reviewsContainer.insertAdjacentHTML('beforeend', html);
                        } else {
                            // Replace all reviews
                            reviewsContainer.innerHTML = html;
                        }
                        
                        // Update or create load more button
                        updateLoadMoreButton();
                    }
                    
                    // Attach event listeners
                    attachReviewEventListeners();
                } else {
                    if (!append) {
                        reviewsContainer.innerHTML = '<div class="alert alert-danger">Failed to load reviews</div>';
                    } else {
                        showMessage('Failed to load more reviews', 'danger');
                    }
                    if (loadMoreBtn) {
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.innerHTML = '<i class="bi bi-arrow-down me-2"></i>Load More Reviews';
                    }
                }
            })
            .catch(error => {
                isLoading = false;
                console.error('Error loading reviews:', error);
                if (!append) {
                    reviewsContainer.innerHTML = '<div class="alert alert-danger">Failed to load reviews</div>';
                } else {
                    showMessage('An error occurred. Please try again.', 'danger');
                }
                if (loadMoreBtn) {
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.innerHTML = '<i class="bi bi-arrow-down me-2"></i>Load More Reviews';
                }
            });
    }
    
    /**
     * Update or create load more button
     */
    function updateLoadMoreButton() {
        const reviewsContainer = document.getElementById('reviews-container');
        if (!reviewsContainer) {
            return;
        }
        
        // Remove existing load more button
        const existingBtn = document.getElementById('load-more-reviews-btn');
        if (existingBtn) {
            existingBtn.remove();
        }
        
        // Add load more button if there are more reviews
        if (hasMore) {
            const loadMoreHtml = `
                <div class="text-center mt-4">
                    <button type="button" id="load-more-reviews-btn" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-down me-2"></i>Load More Reviews
                        <small class="d-block mt-1 text-muted">Showing ${currentPage * 10} of ${totalReviews} reviews</small>
                    </button>
                </div>
            `;
            reviewsContainer.insertAdjacentHTML('beforeend', loadMoreHtml);
            
            // Attach click handler
            const loadMoreBtn = document.getElementById('load-more-reviews-btn');
            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', function() {
                    loadReviews(currentPage + 1, true);
                });
            }
        } else if (totalReviews > 0) {
            // Show "all reviews loaded" message
            const allLoadedHtml = `
                <div class="text-center mt-4 py-3 text-muted">
                    <small><i class="bi bi-check-circle me-1"></i>All ${totalReviews} reviews loaded</small>
                </div>
            `;
            reviewsContainer.insertAdjacentHTML('beforeend', allLoadedHtml);
        }
    }
    
    /**
     * Show review form (for add or edit)
     */
    function showReviewForm(review = null) {
        const formContainer = document.getElementById('review-form-container');
        if (!formContainer) {
            return;
        }
        
        const isEdit = review !== null;
        const formId = isEdit ? 'edit-review-form' : 'add-review-form';
        
        // Check if form already exists
        const existingForm = document.getElementById(formId);
        if (existingForm) {
            existingForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }
        
        // Create form HTML
        const formHtml = `
            <div class="card pg mb-4" id="${formId}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="bi bi-${isEdit ? 'pencil' : 'star'}-fill me-2"></i>
                            ${isEdit ? 'Edit Review' : 'Write a Review'}
                        </h5>
                        <button type="button" class="btn-close" aria-label="Close" onclick="this.closest('.card').remove()"></button>
                    </div>
                    <form id="${formId}-form" data-review-id="${review ? review.id : ''}">
                        <input type="hidden" name="action" value="${isEdit ? 'update' : 'add'}">
                        <input type="hidden" name="listing_id" value="${getListingId()}">
                        ${isEdit ? `<input type="hidden" name="review_id" value="${review.id}">` : ''}
                        
                        <div class="mb-3">
                            <label class="form-label">Rating <span class="text-danger">*</span></label>
                            <div class="rating-input">
                                ${[5, 4, 3, 2, 1].map(i => `
                                    <input type="radio" name="rating" id="rating-${i}-${isEdit ? 'edit' : 'add'}" value="${i}" ${review && review.rating == i ? 'checked' : ''} required>
                                    <label for="rating-${i}-${isEdit ? 'edit' : 'add'}" class="rating-star">
                                        <i class="bi bi-star${i <= (review ? review.rating : 0) ? '-fill' : ''}"></i>
                                    </label>
                                `).join('')}
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comment-${isEdit ? 'edit' : 'add'}" class="form-label">Comment <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="comment-${isEdit ? 'edit' : 'add'}" name="comment" rows="4" required maxlength="1000">${review ? escapeHtml(review.comment) : ''}</textarea>
                            <div class="form-text">Maximum 1000 characters</div>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>${isEdit ? 'Update Review' : 'Submit Review'}
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="this.closest('.card').remove()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        // Insert form
        if (isEdit) {
            // Insert before the review being edited
            const reviewItem = document.querySelector(`[data-review-id="${review.id}"]`);
            if (reviewItem) {
                reviewItem.insertAdjacentHTML('beforebegin', formHtml);
            } else {
                formContainer.insertAdjacentHTML('beforeend', formHtml);
            }
        } else {
            // Insert at the beginning of reviews section
            formContainer.insertAdjacentHTML('afterbegin', formHtml);
        }
        
        // Attach form submit handler
        const reviewForm = document.getElementById(`${formId}-form`);
        if (reviewForm) {
            reviewForm.addEventListener('submit', handleReviewSubmit);
        }
        
        // Attach rating star click handlers
        attachRatingHandlers(isEdit ? 'edit' : 'add');
        
        // Scroll to form
        setTimeout(() => {
            document.getElementById(formId).scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }
    
    /**
     * Attach rating star click handlers
     */
    function attachRatingHandlers(prefix) {
        const ratingInputs = document.querySelectorAll(`input[name="rating"]`);
        ratingInputs.forEach(input => {
            input.addEventListener('change', function() {
                const rating = parseInt(this.value);
                const stars = this.closest('.rating-input').querySelectorAll('.rating-star i');
                stars.forEach((star, index) => {
                    const starValue = index + 1;
                    if (starValue <= rating) {
                        star.className = 'bi bi-star-fill';
                    } else {
                        star.className = 'bi bi-star';
                    }
                });
            });
        });
    }
    
    /**
     * Handle review form submission
     */
    function handleReviewSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        
        // Clear previous errors
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
        
        // Submit review
        fetch(reviewsApiUrl, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Remove form
                    form.closest('.card').remove();
                    
                    // Reset pagination and reload reviews from page 1
                    currentPage = 1;
                    loadReviews(1, false);
                    
                    // Show success message
                    showMessage('Review ' + (formData.get('action') === 'add' ? 'submitted' : 'updated') + ' successfully!', 'success');
                } else {
                    // Show errors
                    if (data.errors) {
                        Object.keys(data.errors).forEach(field => {
                            const input = form.querySelector(`[name="${field}"]`);
                            if (input) {
                                input.classList.add('is-invalid');
                                const feedback = input.closest('.mb-3').querySelector('.invalid-feedback');
                                if (feedback) {
                                    feedback.textContent = data.errors[field];
                                }
                            }
                        });
                    }
                    showMessage(data.message || 'Failed to submit review', 'danger');
                }
            })
            .catch(error => {
                console.error('Error submitting review:', error);
                showMessage('An error occurred. Please try again.', 'danger');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
    }
    
    /**
     * Handle delete review
     */
    function handleDeleteReview(reviewId) {
        if (!confirm('Are you sure you want to delete this review? This action cannot be undone.')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('review_id', reviewId);
        
        fetch(reviewsApiUrl, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Reset pagination and reload reviews from page 1
                    currentPage = 1;
                    loadReviews(1, false);
                    showMessage('Review deleted successfully', 'success');
                } else {
                    showMessage(data.message || 'Failed to delete review', 'danger');
                }
            })
            .catch(error => {
                console.error('Error deleting review:', error);
                showMessage('An error occurred. Please try again.', 'danger');
            });
    }
    
    /**
     * Attach event listeners to review actions
     */
    function attachReviewEventListeners() {
        // Edit buttons
        document.querySelectorAll('.edit-review-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-review-id');
                const reviewItem = this.closest('.review-item');
                
                // Get review data from the DOM
                const rating = reviewItem.querySelectorAll('.bi-star-fill').length;
                const commentEl = reviewItem.querySelector('p');
                const comment = commentEl ? commentEl.textContent.trim() : '';
                const userName = reviewItem.querySelector('.fw-semibold').textContent.trim();
                const createdAt = reviewItem.querySelector('.small .ms-2').textContent.trim();
                
                // Create review object
                const review = {
                    id: reviewId,
                    rating: rating,
                    comment: comment,
                    user_name: userName,
                    created_at: createdAt
                };
                
                showReviewForm(review);
            });
        });
        
        // Delete buttons
        document.querySelectorAll('.delete-review-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-review-id');
                handleDeleteReview(reviewId);
            });
        });
    }
    
    /**
     * Show message to user
     */
    function showMessage(message, type = 'info') {
        // Remove existing messages
        const existing = document.querySelector('.review-message');
        if (existing) {
            existing.remove();
        }
        
        // Create message element
        const messageEl = document.createElement('div');
        messageEl.className = `alert alert-${type} alert-dismissible fade show review-message`;
        messageEl.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Insert at top of reviews section
        const reviewsSection = document.getElementById('reviews-section');
        if (reviewsSection) {
            reviewsSection.insertBefore(messageEl, reviewsSection.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (messageEl.parentNode) {
                    messageEl.remove();
                }
            }, 5000);
        }
    }
    
    /**
     * Initialize reviews functionality
     */
    function init() {
        // Load reviews on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadReviews);
        } else {
            loadReviews();
        }
        
        // Attach "Write Review" button handler
        const writeReviewBtn = document.getElementById('write-review-btn');
        if (writeReviewBtn) {
            writeReviewBtn.addEventListener('click', function() {
                showReviewForm();
            });
        }
    }
    
    // Initialize when script loads
    init();
    
    // Expose functions globally if needed
    window.reviewsManager = {
        loadReviews: loadReviews,
        showReviewForm: showReviewForm,
        handleDeleteReview: handleDeleteReview
    };
    
})();

