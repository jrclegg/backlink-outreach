<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
        }

        .logo {
            font-size: 32px;
            margin-bottom: 20px;
            font-weight: 600;
            color: #2c3e50;
        }

        h1 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 12px;
        }

        .subtitle {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .email {
            background: #f8f9fa;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 24px 0;
            font-family: monospace;
            font-size: 14px;
            color: #2c3e50;
            word-break: break-all;
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 24px 0;
            font-size: 14px;
            border-left: 4px solid #c33;
        }

        .success-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .button {
            background: #3498db;
            color: white;
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
            display: inline-block;
            text-decoration: none;
        }

        .button:hover {
            background: #2980b9;
        }

        .button-secondary {
            background: #95a5a6;
            margin-left: 12px;
        }

        .button-secondary:hover {
            background: #7f8c8d;
        }

        .footer {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #ecf0f1;
            font-size: 12px;
            color: #95a5a6;
        }

        .button-group {
            margin-top: 32px;
        }
    </style>
</head>
<body>
    <div class="container">
        @if(!$valid || isset($error))
            <div class="logo">📧</div>
            <h1>Unsubscribe</h1>
            <div class="error-message">
                {{ $error ?? 'An error occurred' }}
            </div>
            <p class="subtitle">
                If you have questions, please contact us.
            </p>
            <div class="button-group">
                <a href="/" class="button">Go Home</a>
            </div>
        @else
            <div class="success-icon">👋</div>
            <h1>Unsubscribe</h1>
            <p class="subtitle">
                We're sorry to see you go. Click the button below to confirm you want to unsubscribe from future emails.
            </p>

            <div class="email">
                {{ $email }}
            </div>

            <form method="POST" action="{{ route('unsubscribe.store', ['token' => request()->token]) }}" style="display: inline;">
                @csrf
                <div class="button-group">
                    <button type="submit" class="button">Confirm Unsubscribe</button>
                    <a href="/" class="button button-secondary">Keep Subscription</a>
                </div>
            </form>

            <div class="footer">
                <p>You can manage your preferences in your account settings at any time.</p>
            </div>
        @endif
    </div>
</body>
</html>
