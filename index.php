<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get rooms for gallery - limit to available unique images (22 room images)
$rooms_query = "SELECT id, name, room_number FROM rooms WHERE status = 'active' ORDER BY RAND() LIMIT 22";
$rooms_result = $conn->query($rooms_query);

// Get food items for gallery - limit to available unique images (24 food images)
$foods_query = "SELECT id, name, image FROM services WHERE category = 'restaurant' AND status = 'active' ORDER BY RAND() LIMIT 24";
$foods_result = $conn->query($foods_query);

// Room images array - Using your actual images
$room_images = [
    'assets/images/rooms/deluxe/room.jpg',
    'assets/images/rooms/deluxe/room2.jpg',
    'assets/images/rooms/deluxe/room3.jpg',
    'assets/images/rooms/deluxe/room4.jpg',
    'assets/images/rooms/deluxe/room5.jpg',
    'assets/images/rooms/deluxe/room6.jpg',
    'assets/images/rooms/deluxe/room7.jpg',
    'assets/images/rooms/deluxe/room8.jpg',
    'assets/images/rooms/deluxe/room9.jpg',
    'assets/images/rooms/deluxe/room10.jpg',
    'assets/images/rooms/standard/room12.jpg',
    'assets/images/rooms/standard/room13.jpg',
    'assets/images/rooms/standard/room14.jpg',
    'assets/images/rooms/standard/room15.jpg',
    'assets/images/rooms/standard/room16.jpg',
    'assets/images/rooms/suite/room21.jpg',
    'assets/images/rooms/suite/room22.jpg',
    'assets/images/rooms/suite/room23.jpg',
    'assets/images/rooms/family/room27.jpg',
    'assets/images/rooms/family/room28.jpg',
    'assets/images/rooms/presidential/room35.jpg',
    'assets/images/rooms/presidential/room36.jpg',
];

// Food images array - Using your actual images
$food_images = [
    'assets/images/food/ethiopian/food1.jpg',
    'assets/images/food/ethiopian/food2.jpg',
    'assets/images/food/ethiopian/food3.jpg',
    'assets/images/food/ethiopian/food4.jpg',
    'assets/images/food/ethiopian/food5.jpg',
    'assets/images/food/ethiopian/food6.jpg',
    'assets/images/food/ethiopian/food7.jpg',
    'assets/images/food/ethiopian/food8.jpg',
    'assets/images/food/ethiopian/food10.jpg',
    'assets/images/food/ethiopian/food12.jpg',
    'assets/images/food/international/i1.jpg',
    'assets/images/food/international/i2.jpg',
    'assets/images/food/international/i3.jpg',
    'assets/images/food/international/i5.jpg',
    'assets/images/food/international/i6.jpg',
    'assets/images/food/international/i7.jpg',
    'assets/images/food/international/i8.jpg',
    'assets/images/food/international/i10.jpg',
    'assets/images/food/beverages/b1.jpg',
    'assets/images/food/beverages/b2.jpg',
    'assets/images/food/beverages/b3.jpg',
    'assets/images/food/beverages/b5.jpg',
    'assets/images/food/beverages/b7.jpg',
    'assets/images/food/beverages/b9.jpg',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harar Ras Hotel - Welcome</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* Gallery Card Styles - Responsive Grid Layout */
        .gallery-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }
        
        .gallery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .gallery-card-image {
            width: 100%;
            height: 280px;
            overflow: hidden;
            background: #f5f5f5;
        }
        
        .gallery-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .gallery-card-body {
            padding: 15px;
        }
        
        .gallery-card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--slate-dark);
            margin: 0 0 8px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .gallery-card-description {
            font-size: 0.85rem;
            line-height: 1.4;
            color: #6c757d;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .gallery-card-image {
                height: 240px;
            }
        }
        
        @media (max-width: 768px) {
            .gallery-card-image {
                height: 220px;
            }
            
            .gallery-card-title {
                font-size: 0.95rem;
            }
            
            .gallery-card-description {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 576px) {
            .gallery-card-image {
                height: 200px;
            }
            
            .gallery-card-title {
                font-size: 0.9rem;
            }
            
            .gallery-card-description {
                font-size: 0.75rem;
            }
            
            .gallery-card-body {
                padding: 12px;
            }
        }
        
        /* Hero Section */
        .hero-section {
            padding: 80px 0;
            color: white;
            text-align: center;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .hero-subtitle {
            font-size: 1.5rem;
            margin-bottom: 30px;
            opacity: 0.9;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            .hero-subtitle {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Hero Section -->
    <section class="hero-section" style="background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('assets/images/hotel/exterior/hotel-main.png') center/cover no-repeat; min-height: 500px; display: flex; align-items: center;">
        <div class="container">
            <h1 class="hero-title">Welcome to Harar Ras Hotel</h1>
            <p class="hero-subtitle">Experience Luxury & Comfort</p>
            <div class="d-flex gap-3 justify-content-center">
                <a href="rooms.php" class="btn btn-light btn-lg">
                    <i class="fas fa-bed me-2"></i>View All Rooms
                </a>
                <a href="services.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-concierge-bell me-2"></i>Our Services
                </a>
            </div>
        </div>
    </section>
    
    <!-- Gallery Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-2">Discover Our Spaces</h2>
                <p class="text-muted mb-3">Explore our beautiful rooms and delicious cuisine</p>
                <p class="text-muted small">Experience comfort and luxury in every corner of our hotel</p>
            </div>
            
            <div class="row g-4 justify-content-center">
                <?php
                // Mix rooms and foods randomly
                $gallery_items = [];
                
                // Room descriptions mapping
                $room_descriptions = [
                    'Standard Single Room' => 'Cozy and comfortable space perfect for solo travelers',
                    'Standard Double Room' => 'Ideal for couples seeking comfort and convenience',
                    'Deluxe Single Room' => 'Premium amenities with stunning city views',
                    'Deluxe Double Room' => 'Elegant furnishings in a spacious setting',
                    'Double (King Size)' => 'Luxurious king-size comfort for ultimate relaxation',
                    'Suite Room' => 'Spacious suite with separate living area',
                    'Family (Team Bed)' => 'Perfect family accommodation with multiple beds',
                    'Executive Suite' => 'Executive luxury with premium services',
                    'Presidential Suite' => 'Ultimate luxury with panoramic views'
                ];
                
                // Food descriptions mapping
                $food_descriptions = [
                    'Ethiopian Traditional Platter' => 'Authentic Ethiopian flavors on a traditional platter',
                    'Ethiopian Breakfast' => 'Start your day with traditional Ethiopian breakfast',
                    'Ethiopian Coffee Ceremony' => 'Experience our authentic coffee ceremony',
                    'Ethiopian Lunch Special' => 'Delicious lunch featuring local specialties',
                    'International Breakfast Buffet' => 'Wide selection of international breakfast items',
                    'International Lunch Buffet' => 'Global cuisine for your midday meal',
                    'International Dinner Buffet' => 'Premium dinner buffet with diverse options',
                    'International Weekend Brunch' => 'Leisurely weekend brunch experience'
                ];
                
                // Add room images - assign one unique image per room
                $room_index = 0;
                while ($room = $rooms_result->fetch_assoc()) {
                    // Only add if we have a unique image available
                    if ($room_index < count($room_images)) {
                        $room_name = $room['name'];
                        $description = $room_descriptions[$room_name] ?? 'Comfortable accommodation with modern amenities';
                        
                        $gallery_items[] = [
                            'type' => 'room',
                            'name' => $room['name'],
                            'description' => $description,
                            'image' => $room_images[$room_index],
                            'link' => 'rooms.php',
                            'id' => $room['id']
                        ];
                    }
                    $room_index++;
                }
                
                // Add food images - assign one unique image per food item
                $food_index = 0;
                while ($food = $foods_result->fetch_assoc()) {
                    // Only add if we have a unique image available
                    if ($food_index < count($food_images)) {
                        $food_name = $food['name'];
                        $description = $food_descriptions[$food_name] ?? 'Delicious cuisine prepared by our expert chefs';
                        
                        $gallery_items[] = [
                            'type' => 'food',
                            'name' => $food['name'],
                            'description' => $description,
                            'image' => !empty($food['image']) ? $food['image'] : $food_images[$food_index],
                            'link' => 'services.php#restaurant',
                            'id' => $food['id']
                        ];
                    }
                    $food_index++;
                }
                
                // Shuffle for random display
                shuffle($gallery_items);
                
                // Display gallery items - show exactly 9 items (3 rows x 3 columns)
                $count = 0;
                foreach ($gallery_items as $item) {
                    if ($count >= 9) break; // Show exactly 9 items
                    echo '<div class="col-12 col-sm-6 col-md-4 col-lg-4">';
                    echo '<div class="gallery-card" onclick="window.location.href=\'' . $item['link'] . '\'">';
                    echo '<div class="gallery-card-image">';
                    echo '<img src="' . htmlspecialchars($item['image']) . '" alt="' . htmlspecialchars($item['name']) . '" loading="lazy">';
                    echo '</div>';
                    echo '<div class="gallery-card-body">';
                    echo '<h6 class="gallery-card-title">' . htmlspecialchars($item['name']) . '</h6>';
                    echo '<p class="gallery-card-description text-muted small mb-0">' . htmlspecialchars($item['description']) . '</p>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                    $count++;
                }
                ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="rooms.php" class="btn btn-gold btn-lg">
                    <i class="fas fa-eye me-2"></i>View All
                </a>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script></script>
</body>
</html>
