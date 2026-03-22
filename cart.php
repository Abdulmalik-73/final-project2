<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                <div class="col text-center">
                    <h1 class="display-4 fw-bold mb-3">Shopping Cart</h1>
                    <p class="lead text-muted">Review your selected items</p>
                </div>
                <div class="col-auto">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </section>
    
    <!-- Cart Content -->
    <section class="py-5">
        <div class="container">
            <!-- Cart Items Container -->
            <div id="cartContainer">
                <!-- Empty Cart Message -->
                <div id="emptyCart" class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-5x text-muted mb-4"></i>
                    <h3 class="mb-3">Your cart is empty</h3>
                    <p class="text-muted mb-4">Add some rooms or services to get started!</p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="rooms.php" class="btn btn-gold">
                            <i class="fas fa-bed me-2"></i>Browse Rooms
                        </a>
                        <a href="services.php" class="btn btn-outline-gold">
                            <i class="fas fa-concierge-bell me-2"></i>View Services
                        </a>
                    </div>
                </div>
                
                <!-- Cart Items List -->
                <div id="cartItems" style="display: none;">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-gold text-white">
                                    <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Cart Items</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div id="cartItemsList">
                                        <!-- Cart items will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <!-- Cart Summary -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Order Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <span id="cartSubtotal">$0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Tax (10%):</span>
                                        <span id="cartTax">$0.00</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between fw-bold fs-5">
                                        <span>Total:</span>
                                        <span id="cartTotal" class="text-gold">$0.00</span>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <?php if (is_logged_in()): ?>
                                            <button class="btn btn-gold w-100 mb-2" onclick="proceedToCheckout()">
                                                <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                                            </button>
                                        <?php else: ?>
                                            <a href="login.php?redirect=cart" class="btn btn-gold w-100 mb-2">
                                                <i class="fas fa-sign-in-alt me-2"></i>Login to Checkout
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-outline-secondary w-100" onclick="clearCart()">
                                            <i class="fas fa-trash me-2"></i>Clear Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Promo Code -->
                            <div class="card border-0 shadow-sm mt-3">
                                <div class="card-body">
                                    <h6 class="mb-3"><i class="fas fa-tag me-2"></i>Promo Code</h6>
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Enter promo code" id="promoCode">
                                        <button class="btn btn-outline-gold" onclick="applyPromoCode()">Apply</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    
    <script>
        // Cart functionality
        let cart = JSON.parse(localStorage.getItem('hotelCart')) || [];
        
        // Load cart on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCart();
            updateCartBadge();
        });
        
        function loadCart() {
            const cartContainer = document.getElementById('cartContainer');
            const emptyCart = document.getElementById('emptyCart');
            const cartItems = document.getElementById('cartItems');
            const cartItemsList = document.getElementById('cartItemsList');
            
            if (cart.length === 0) {
                emptyCart.style.display = 'block';
                cartItems.style.display = 'none';
                return;
            }
            
            emptyCart.style.display = 'none';
            cartItems.style.display = 'block';
            
            let cartHTML = '';
            let subtotal = 0;
            
            cart.forEach((item, index) => {
                const itemTotal = item.price * (item.quantity || 1);
                subtotal += itemTotal;
                
                cartHTML += `
                    <div class="border-bottom p-3">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 class="mb-1">${item.name || item.roomName}</h6>
                                <small class="text-muted">
                                    ${item.roomNumber ? `Room #${item.roomNumber}` : ''}
                                    ${item.category ? `Category: ${item.category}` : ''}
                                </small>
                            </div>
                            <div class="col-md-2 text-center">
                                <div class="input-group input-group-sm">
                                    <button class="btn btn-outline-secondary" onclick="updateQuantity(${index}, -1)">-</button>
                                    <input type="text" class="form-control text-center" value="${item.quantity || 1}" readonly>
                                    <button class="btn btn-outline-secondary" onclick="updateQuantity(${index}, 1)">+</button>
                                </div>
                            </div>
                            <div class="col-md-2 text-center">
                                <span class="fw-bold">$${item.price.toFixed(2)}</span>
                            </div>
                            <div class="col-md-2 text-center">
                                <span class="fw-bold text-gold">$${itemTotal.toFixed(2)}</span>
                            </div>
                            <div class="col-md-1 text-center">
                                <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            cartItemsList.innerHTML = cartHTML;
            updateCartSummary(subtotal);
        }
        
        function updateCartSummary(subtotal) {
            const tax = subtotal * 0.1;
            const total = subtotal + tax;
            
            document.getElementById('cartSubtotal').textContent = `$${subtotal.toFixed(2)}`;
            document.getElementById('cartTax').textContent = `$${tax.toFixed(2)}`;
            document.getElementById('cartTotal').textContent = `$${total.toFixed(2)}`;
        }
        
        function updateQuantity(index, change) {
            if (cart[index]) {
                cart[index].quantity = (cart[index].quantity || 1) + change;
                if (cart[index].quantity <= 0) {
                    cart.splice(index, 1);
                }
                localStorage.setItem('hotelCart', JSON.stringify(cart));
                loadCart();
                updateCartBadge();
            }
        }
        
        function removeFromCart(index) {
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                cart.splice(index, 1);
                localStorage.setItem('hotelCart', JSON.stringify(cart));
                loadCart();
                updateCartBadge();
                showNotification('Item removed from cart', 'success');
            }
        }
        
        function clearCart() {
            if (confirm('Are you sure you want to clear your entire cart?')) {
                cart = [];
                localStorage.setItem('hotelCart', JSON.stringify(cart));
                loadCart();
                updateCartBadge();
                showNotification('Cart cleared successfully', 'success');
            }
        }
        
        function updateCartBadge() {
            const badge = document.querySelector('.cart-badge');
            if (badge) {
                badge.textContent = cart.length;
                badge.style.display = cart.length > 0 ? 'inline-block' : 'none';
            }
        }
        
        function proceedToCheckout() {
            if (cart.length === 0) {
                showNotification('Your cart is empty!', 'warning');
                return;
            }
            
            // Store cart data in session and redirect to booking
            localStorage.setItem('checkoutCart', JSON.stringify(cart));
            window.location.href = 'checkout.php';
        }
        
        function applyPromoCode() {
            const promoCode = document.getElementById('promoCode').value.trim();
            if (promoCode) {
                // Here you can add promo code validation logic
                showNotification('Promo code functionality coming soon!', 'info');
            }
        }
        
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'error' ? 'danger' : 'info'} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }
    </script>
</body>
</html>