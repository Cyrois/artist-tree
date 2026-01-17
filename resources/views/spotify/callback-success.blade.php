<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spotify Connected</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #1DB954 0%, #191414 100%);
            color: white;
        }

        .container {
            text-align: center;
            padding: 2rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        p {
            opacity: 0.8;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="spinner"></div>
        <h1>Connected to Spotify!</h1>
        <p>This window will close automatically...</p>
    </div>

    <script>
        (function () {
            const success = @json($success ?? true);
            const error = @json($error ?? null);

            if (window.opener) {
                // Notify parent window
                window.opener.postMessage({
                    spotify_authenticated: success,
                    error: error
                }, window.location.origin);

                // Close popup after short delay for visual feedback
                setTimeout(() => window.close(), 500);
            } else {
                // Fallback: opened directly (not as popup)
                window.location.href = @json($returnUrl ?? '/dashboard');
            }
        })();
    </script>
</body>

</html>