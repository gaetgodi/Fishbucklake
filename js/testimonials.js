/**
 * Testimonials Display JavaScript
 * Fetches from WordPress REST API and displays testimonials
 * 
 * Uses same pattern as FAQ system
 */

(function() {
    'use strict';
    
    const restApiUrl = '/wp-json/wp/v2/testimonials?per_page=100&orderby=date&order=desc';
    
    let testimonialData = [];
    let categories = new Set(['all']);

    /**
     * Load testimonials from REST API
     */
    async function loadTestimonials() {
        try {
            const response = await fetch(restApiUrl);
            
            if (!response.ok) {
                throw new Error('Failed to load testimonials: ' + response.status);
            }
            
            const testimonials = await response.json();
            
            // Transform REST API data
            testimonialData = testimonials.map(function(testimonial) {
                const category = testimonial.category_names && testimonial.category_names.length > 0 
                    ? testimonial.category_names[0] 
                    : 'General';
                
                categories.add(category);
                
                return {
                    title: testimonial.title.rendered,
                    content: testimonial.content.rendered,
                    author: testimonial.author_info.name || '',
                    location: testimonial.author_info.location || '',
                    rating: testimonial.author_info.rating || '',
                    category: category,
                    date: testimonial.date
                };
            });
            
            if (testimonialData.length === 0) {
                document.getElementById('testimonialsContainer').innerHTML = 
                    '<div class="testimonials-no-results">No testimonials available yet.</div>';
                return;
            }
            
            buildCategoryFilters();
            renderTestimonials();
            filterByCategory('all');
            
        } catch (error) {
            document.getElementById('testimonialsContainer').innerHTML = 
                '<div style="color: var(--fbl-gold); padding: 40px; text-align: center;">Error loading testimonials: ' + 
                error.message + '</div>';
            console.error('Testimonials Load Error:', error);
        }
    }

    /**
     * Build category filter buttons
     */
    function buildCategoryFilters() {
        const filtersContainer = document.getElementById('testimonialFilters');
        
        if (categories.size <= 2) {
            // Only "all" and maybe one category - hide filters
            return;
        }
        
        filtersContainer.style.display = 'block';
        
        const sortedCategories = Array.from(categories)
            .filter(function(cat) {
                return cat && cat !== 'all';
            })
            .sort();
        
        sortedCategories.forEach(function(category) {
            const button = document.createElement('button');
            button.className = 'testimonial-filter-btn';
            button.setAttribute('data-category', category);
            button.textContent = category;
            
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                document.querySelectorAll('.testimonial-filter-btn').forEach(function(btn) {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                button.classList.add('active');
                
                // Filter testimonials
                filterByCategory(category);
            });
            
            filtersContainer.appendChild(button);
        });
    }

    /**
     * Filter testimonials by category
     */
    function filterByCategory(category) {
        const items = document.querySelectorAll('.testimonial-item');
        let visibleCount = 0;
        
        items.forEach(function(item) {
            const itemCategory = item.getAttribute('data-category');
            
            if (category === 'all' || itemCategory === category) {
                item.style.display = 'block';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        const noResults = document.getElementById('noResults');
        if (visibleCount === 0) {
            noResults.style.display = 'block';
        } else {
            noResults.style.display = 'none';
        }
    }

    /**
     * Render all testimonials
     */
    function renderTestimonials() {
        const container = document.getElementById('testimonialsContainer');
        container.innerHTML = '';
        
        testimonialData.forEach(function(testimonial) {
            const item = document.createElement('div');
            item.className = 'testimonial-item';
            item.setAttribute('data-category', testimonial.category);
            
            // Build testimonial HTML
            let html = '<div class="testimonial-content">' + testimonial.content + '</div>';
            
            html += '<div class="testimonial-author">';
            
            // Author name
            if (testimonial.author) {
                html += '<div class="testimonial-author-name">' + testimonial.author + '</div>';
            }
            
            // Location
            if (testimonial.location) {
                html += '<div class="testimonial-author-location">' + testimonial.location + '</div>';
            }
            
            // Rating stars
            if (testimonial.rating) {
                html += '<div class="testimonial-rating">';
                for (let i = 0; i < parseInt(testimonial.rating); i++) {
                    html += '<span class="star">⭐</span>';
                }
                html += '</div>';
            }
            
            // Category badge (optional)
            if (categories.size > 2) {
                html += '<div class="testimonial-category">' + testimonial.category + '</div>';
            }
            
            html += '</div>'; // Close testimonial-author
            
            item.innerHTML = html;
            container.appendChild(item);
        });
    }

    /**
     * Initialize
     */
    function init() {
        if (document.getElementById('testimonialsContainer')) {
            loadTestimonials();
        }
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();