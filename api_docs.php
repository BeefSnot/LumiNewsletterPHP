<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';

// Get site URL from settings
$settingsResult = $db->query("SELECT value FROM settings WHERE name = 'site_url'");
if ($settingsResult && $settingsResult->num_rows > 0) {
    $siteUrl = $settingsResult->fetch_assoc()['value'];
} else {
    // Fallback to auto-detected URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    $siteUrl = $protocol . $host . rtrim($path, '/');
}

// API base URL
$apiBaseUrl = $siteUrl . '/api.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .api-method {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            color: white;
            font-size: 12px;
            font-weight: bold;
            margin-right: 8px;
        }
        .method-get { background-color: #4CAF50; }
        .method-post { background-color: #2196F3; }
        .method-put { background-color: #FF9800; }
        .method-delete { background-color: #F44336; }
        
        .endpoint {
            background-color: #f5f7fa;
            border-radius: 4px;
            padding: 8px 12px;
            font-family: monospace;
            margin: 10px 0;
            overflow-x: auto;
        }
        
        .code-block {
            background-color: #272822;
            color: #f8f8f2;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            overflow-x: auto;
        }
        
        .parameter-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .parameter-table th, .parameter-table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .parameter-table th {
            background-color: #f5f7fa;
        }
        
        .api-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .api-section:last-child {
            border-bottom: none;
        }
        
        .response-example {
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <!-- Mobile navigation toggle button -->
    <button class="mobile-nav-toggle" id="mobileNavToggle">
        <i class="fas fa-bars" id="menuIcon"></i>
    </button>
    
    <!-- Backdrop for mobile menu -->
    <div class="backdrop" id="backdrop"></div>
    
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>API Documentation</h1>
                </div>
            </header>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-book"></i> LumiNewsletter API Reference</h2>
                </div>
                <div class="card-body">
                    <p>
                        This documentation describes how to use the LumiNewsletter API to integrate with your applications.
                        To use the API, you need to generate an API key in the <a href="api_keys.php">API Keys</a> section.
                    </p>
                    
                    <div class="api-section">
                        <h3>Authentication</h3>
                        <p>All API requests require authentication using your API key. Include your API key in the request header:</p>
                        <div class="code-block">
                            X-API-Key: your_api_key_here
                        </div>
                    </div>
                    
                    <div class="api-section">
                        <h3>API Status</h3>
                        
                        <div>
                            <span class="api-method method-get">GET</span>
                            <span class="endpoint"><?php echo htmlspecialchars($apiBaseUrl); ?>/status</span>
                        </div>
                        
                        <p>Check the API status and your authentication.</p>
                        
                        <h4>Response Example:</h4>
                        <div class="code-block response-example">
{
    "status": "ok",
    "version": "1.0.0",
    "timestamp": "2025-04-29 10:15:30",
    "user_id": 1,
    "role": "admin"
}
                        </div>
                    </div>
                    
                    <div class="api-section">
                        <h3>Subscribers</h3>
                        
                        <h4>Get All Subscribers</h4>
                        <div>
                            <span class="api-method method-get">GET</span>
                            <span class="endpoint"><?php echo htmlspecialchars($apiBaseUrl); ?>/subscribers</span>
                        </div>
                        
                        <p>Retrieve a paginated list of subscribers.</p>
                        
                        <h5>Query Parameters:</h5>
                        <table class="parameter-table">
                            <thead>
                                <tr>
                                    <th>Parameter</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>page</td>
                                    <td>Integer</td>
                                    <td>Page number (default: 1)</td>
                                </tr>
                                <tr>
                                    <td>limit</td>
                                    <td>Integer</td>
                                    <td>Items per page (default: 20, max: 100)</td>
                                </tr>
                                <tr>
                                    <td>search</td>
                                    <td>String</td>
                                    <td>Search term to filter subscribers</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <h5>Response Example:</h5>
                        <div class="code-block response-example">
{
    "subscribers": [
        {
            "id": 1,
            "email": "john@example.com",
            "name": "John Doe",
            "created_at": "2025-04-20 14:30:00",
            "status": "active"
        },
        {
            "id": 2,
            "email": "jane@example.com",
            "name": "Jane Smith",
            "created_at": "2025-04-21 09:45:00",
            "status": "active"
        }
    ],
    "pagination": {
        "total": 243,
        "page": 1,
        "limit": 20,
        "pages": 13
    }
}
                        </div>
                        
                        <h4>Get Subscriber Count</h4>
                        <div>
                            <span class="api-method method-get">GET</span>
                            <span class="endpoint"><?php echo htmlspecialchars($apiBaseUrl); ?>/subscribers/count</span>
                        </div>
                        
                        <p>Get the total count of subscribers.</p>
                        
                        <h5>Response Example:</h5>
                        <div class="code-block response-example">
{
    "count": 243
}
                        </div>
                        
                        <h4>Get Specific Subscriber</h4>
                        <div>
                            <span class="api-method method-get">GET</span>
                            <span class="endpoint"><?php echo htmlspecialchars($apiBaseUrl); ?>/subscribers/{id}</span>
                        </div>
                        
                        <p>Retrieve information about a specific subscriber.</p>
                        
                        <h5>Response Example:</h5>
                        <div class="code-block response-example">
{
    "id": 1,
    "email": "john@example.com",
    "name": "John Doe",
    "created_at": "2025-04-20 14:30:00",
    "status": "active",
    "groups": [
        {"id": 1, "name": "Marketing Updates"},
        {"id": 3, "name": "Product News"}
    ]
}
                        </div>
                        
                        <h4>Add New Subscriber</h4>
                        <div>
                            <span class="api-method method-post">POST</span>
                            <span class="endpoint"><?php echo htmlspecialchars($apiBaseUrl); ?>/subscribers</span>
                        </div>
                        
                        <p>Add a new subscriber to your list.</p>
                        
                        <h5>Request Body:</h5>
                        <div class="code-block">
{
    "email": "new@example.com",
    "name": "New Subscriber",
    "group_id": 2  // Optional: Add to a specific group
}
                        </div>
                        
                        <h5>Response Example:</h5>
                        <div class="code-block response-example">
{
    "id": 244,
    "email": "new@example.com",
    "name": "New Subscriber",
    "message": "Subscriber added successfully"
}
                        </div>
                    </div>
                    
                    <div class="api-section">
                        <h3>Newsletters</h3>
                        
                        <h4>Get All Newsletters</h4>
                        <div>
                            <span class="api-method method-get">GET</span>
                            <span class="endpoint"><?php echo htmlspecialchars($apiBaseUrl); ?>/newsletters</span>
                        </div>
                        
                        <p>Retrieve a paginated list of newsletters.</p>
                        
                        <h5>Query Parameters:</h5>
                        <table class="parameter-table">
                            <thead>
                                <tr>
                                    <th>Parameter</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>page</td>
                                    <td>Integer</td>
                                    <td>Page number (default: 1)</td>
                                </tr>
                                <tr>
                                    <td>limit</td>
                                    <td>Integer</td>
                                    <td>Items per page (default: 10)</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <h5>Response Example:</h5>
                        <div class="code-block response-example">
{
    "newsletters": [
        {
            "id": 12,
            "subject": "April Newsletter",
            "sender_name": "admin",
            "created_at": "2025-04-20 15:30:00",
            "sent_at": "2025-04-20 16:00:00",
            "opens": 156,
            "clicks": 42
        },
        {
            "id": 11,
            "subject": "March Product Updates",
            "sender_name": "admin",
            "created_at": "2025-03-15 10:20:00",
            "sent_at": "2025-03-15 11:00:00",
            "opens": 203,
            "clicks": 87
        }
    ],
    "pagination": {
        "total": 12,
        "page": 1,
        "limit": 10,
        "pages": 2
    }
}
                        </div>
                        
                        <h4>Get Newsletter Statistics</h4>
                        <div>
                            <span class="api-method method-get">GET</span>
                            <span class="endpoint"><?php echo htmlspecialchars($apiBaseUrl); ?>/newsletters/stats</span>
                        </div>
                        
                        <p>Get overall newsletter statistics.</p>
                        
                        <h5>Response Example:</h5>
                        <div class="code-block response-example">
{
    "total_newsletters": 12,
    "total_opens": 1842,
    "total_clicks": 523,
    "subscribers_count": 243
}
                        </div>
                        
                        <h4>Get Specific Newsletter</h4>
                        <div>
                            <span class="api-method method-get">GET</span>
                            <span class="endpoint"><?php echo htmlspecialchars($apiBaseUrl); ?>/newsletters/{id}</span>
                        </div>
                        
                        <p>Get detailed information about a specific newsletter.</p>
                        
                        <h5>Response Example:</h5>
                        <div class="code-block response-example">
{
    "id": 12,
    "subject": "April Newsletter",
    "body": "<!DOCTYPE html><html><body>Newsletter content...</body></html>",
    "sender_id": 1,
    "sender_name": "admin",
    "created_at": "2025-04-20 15:30:00",
    "sent_at": "2025-04-20 16:00:00",
    "groups": [
        {"id": 1, "name": "Marketing Updates"},
        {"id": 2, "name": "Weekly Digest"}
    ],
    "stats": {
        "opens": 156,
        "clicks": 42
    }
}
                        </div>
                    </div>
                    
                    <div class="api-section">
                        <h3>Analytics</h3>
                        
                        <h4>Get Newsletter Analytics</h4>
                        <div>
                            <span class="api-method method-get">GET</span>
                            <span class="endpoint"><?php echo htmlspecialchars($apiBaseUrl); ?>/analytics/newsletter/{id}</span>
                        </div>
                        
                        <p>Get detailed analytics for a specific newsletter.</p>
                        
                        <h5>Response Example:</h5>
                        <div class="code-block response-example">
{
    "newsletter_id": 12,
    "subject": "April Newsletter",
    "stats": {
        "sent": 243,
        "opens": {
            "total": 156,
            "unique": 132,
            "rate": 64.2
        },
        "clicks": {
            "total": 42,
            "unique": 38,
            "rate": 17.3
        }
    },
    "top_links": [
        {
            "url": "https://example.com/product",
            "clicks": 18
        },
        {
            "url": "https://example.com/special",
            "clicks": 12
        }
    ],
    "geo_data": [
        {
            "country": "United States",
            "count": 86
        },
        {
            "country": "United Kingdom",
            "count": 25
        }
    ]
}
                        </div>
                    </div>
                    
                    <div class="api-section">
                        <h3>Social Sharing</h3>
                        
                        <h4>Get Social Sharing Statistics</h4>
                        <div>
                            <span class="api-method method-get">GET</span>
                            <span class="endpoint"><?php echo htmlspecialchars($apiBaseUrl); ?>/social/stats</span>
                        </div>
                        
                        <p>Get overall social sharing statistics.</p>
                        
                        <h5>Response Example:</h5>
                        <div class="code-block response-example">
{
    "platforms": {
        "facebook": {
            "shares": 87,
            "clicks": 124
        },
        "twitter": {
            "shares": 53,
            "clicks": 76
        },
        "linkedin": {
            "shares": 42,
            "clicks": 51
        },
        "email": {
            "shares": 29,
            "clicks": 18
        }
    },
    "total_shares": 211,
    "total_clicks": 269
}
                        </div>
                        
                        <h4>Get Newsletter Social Sharing</h4>
                        <div>
                            <span class="api-method method-get">GET</span>
                            <span class="endpoint"><?php echo htmlspecialchars($apiBaseUrl); ?>/social/newsletter/{id}</span>
                        </div>
                        
                        <p>Get social sharing statistics for a specific newsletter.</p>
                        
                        <h5>Response Example:</h5>
                        <div class="code-block response-example">
{
    "newsletter_id": 12,
    "subject": "April Newsletter",
    "platforms": {
        "facebook": {
            "shares": 15,
            "clicks": 23
        },
        "twitter": {
            "shares": 8,
            "clicks": 12
        },
        "linkedin": {
            "shares": 7,
            "clicks": 9
        },
        "email": {
            "shares": 4,
            "clicks": 2
        }
    },
    "total_shares": 34,
    "total_clicks": 46
}
                        </div>
                    </div>
                    
                    <div class="api-section">
                        <h3>Code Examples</h3>
                        
                        <h4>JavaScript/jQuery Example</h4>
                        <div class="code-block">
$.ajax({
    url: '<?php echo htmlspecialchars($apiBaseUrl); ?>/subscribers',
    type: 'GET',
    headers: {
        'X-API-Key': 'your_api_key_here'
    },
    success: function(data) {
        console.log('Subscribers:', data);
    },
    error: function(xhr, status, error) {
        console.error('API Error:', error);
    }
});
                        </div>
                        
                        <h4>PHP Example</h4>
                        <div class="code-block">
$ch = curl_init('<?php echo htmlspecialchars($apiBaseUrl); ?>/subscribers');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: your_api_key_here'
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

print_r($data);
                        </div>
                        
                        <h4>Python Example</h4>
                        <div class="code-block">
import requests

url = '<?php echo htmlspecialchars($apiBaseUrl); ?>/subscribers'
headers = {
    'X-API-Key': 'your_api_key_here'
}

response = requests.get(url, headers=headers)
data = response.json()

print(data)
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/sidebar.js"></script>
</body>
</html>