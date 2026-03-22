/**
 * Star Rating Component
 * Reusable star rating system for Hotel Management System
 * Works for: Room booking, Food ordering, Spa services, Laundry services
 */

class StarRating {
    constructor(container) {
        this.container = container;
        this.stars = container.querySelectorAll('.star');
        this.inputName = container.dataset.name || container.dataset.rating;
        this.hiddenInput = document.querySelector(`input[name="${this.inputName}"]`);
        this.textElement = document.getElementById(`${this.inputName}_text`);
        this.currentValue = 0;
        
        this.ratingTexts = {
            1: 'Poor',
            2: 'Fair',
            3: 'Good',
            4: 'Very Good',
            5: 'Excellent'
        };
        
        this.init();
    }
    
    init() {
        if (!this.hiddenInput) {
            console.error(`Hidden input not found for: ${this.inputName}`);
            return;
        }
        
        // Add click event to each star
        this.stars.forEach((star, index) => {
            star.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.setRating(index + 1);
            });
            
            star.addEventListener('mouseenter', () => {
                this.highlightStars(index + 1);
            });
        });
        
        // Reset to current value on mouse leave
        this.container.addEventListener('mouseleave', () => {
            this.highlightStars(this.currentValue);
        });
        
        // Initialize from hidden input if value exists
        const initialValue = parseInt(this.hiddenInput.value);
        if (initialValue > 0 && initialValue <= 5) {
            this.setRating(initialValue);
        }
    }
    
    setRating(value) {
        this.currentValue = value;
        this.hiddenInput.value = value;
        this.highlightStars(value);
        this.updateText(value);
        
        // Trigger change event for validation
        this.hiddenInput.dispatchEvent(new Event('change'));
        
        // Visual feedback
        this.container.classList.add('rated');
        setTimeout(() => {
            this.container.classList.remove('rated');
        }, 300);
    }
    
    highlightStars(value) {
        this.stars.forEach((star, index) => {
            if (index < value) {
                star.classList.add('active');
                star.style.color = '#ffc107';
            } else {
                star.classList.remove('active');
                star.style.color = '#ddd';
            }
        });
    }
    
    updateText(value) {
        if (this.textElement) {
            this.textElement.textContent = this.ratingTexts[value] || 'Click to rate';
            this.textElement.style.color = '#28a745';
            this.textElement.style.fontWeight = '600';
        }
    }
    
    reset() {
        this.currentValue = 0;
        this.hiddenInput.value = '';
        this.highlightStars(0);
        if (this.textElement) {
            this.textElement.textContent = 'Click to rate';
            this.textElement.style.color = '#666';
            this.textElement.style.fontWeight = 'normal';
        }
    }
    
    getValue() {
        return this.currentValue;
    }
}

// Initialize all star ratings when DOM is ready
function initializeStarRatings() {
    const containers = document.querySelectorAll('.star-rating-container, .star-rating');
    
    if (containers.length === 0) {
        console.warn('No star rating containers found');
        return;
    }
    
    const ratings = [];
    containers.forEach(container => {
        try {
            const rating = new StarRating(container);
            ratings.push(rating);
        } catch (error) {
            console.error('Error initializing star rating:', error);
        }
    });
    
    console.log(`Initialized ${ratings.length} star rating components`);
    return ratings;
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeStarRatings);
} else {
    initializeStarRatings();
}

// Export for manual initialization if needed
window.StarRating = StarRating;
window.initializeStarRatings = initializeStarRatings;
