<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Chromebook Kiosk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #181A20; color: #fff; }
        .kiosk-container { min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .kiosk-buttons { display: flex; gap: 3em; }
        .kiosk-btn {
            background: #23272F;
            color: #ffcb4b;
            border: none;
            border-radius: 28px;
            width: 360px;
            height: 250px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            box-shadow: 0 4px 24px #0006;
            cursor: pointer;
            transition: transform 0.08s, box-shadow 0.08s;
            margin-bottom: 0;
        }
        .kiosk-btn:focus,
        .kiosk-btn:hover {
            transform: scale(1.04);
            background: #282c34;
            outline: none;
            box-shadow: 0 8px 32px #000a;
        }
        .kiosk-btn .kiosk-icon {
            font-size: 6rem;
            line-height: 1;
            margin-bottom: 0.5em;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .kiosk-btn span {
            font-size: 2.1rem;
            font-weight: 700;
            text-align: center;
        }
        @media (max-width: 900px) {
            .kiosk-buttons { flex-direction: column; gap: 2em; }
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <div class="kiosk-buttons">
        <form action="kiosk_loaner.php" method="get">
            <button type="submit" class="kiosk-btn">
                <div class="kiosk-icon">
                    <i class="fa-solid fa-right-left"></i>
                </div>
                <span>Loaner Check In / Check Out</span>
            </button>
        </form>
        <form action="kiosk_workorder.php" method="get">
            <button type="submit" class="kiosk-btn">
                <div class="kiosk-icon">
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <span>Chromebook Workorder</span>
            </button>
        </form>
    </div>
</div>
</body>
</html>
