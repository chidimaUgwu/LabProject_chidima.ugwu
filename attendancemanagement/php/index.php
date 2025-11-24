<?php
session_start();

// If logged in → redirect
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) 
{

    if ($_SESSION['role'] === 'faculty') 
    {
        header('Location: faculty_dashboard.php');
    } 
    elseif ($_SESSION['role'] === 'lecturer') 
    {
        header('Location: lecturer_dashboard.php');
    } 
    else 
    {
        header('Location: student_dashboard.php');
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <title>Welcome Page</title>

    <style>
        body {
            font-family: Verdana, Geneva, Tahoma, sans-serif;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        .containerWelcome {
            text-align: center;
            width: 60%;
            margin: 5% auto;
            background-color: rgb(244, 237, 197);
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0px 8px 25px rgba(0,0,0,0.3);
        }

        .containerWelcome img {
            width: 350px;
        }

        .container {
            margin-top: -40px;
        }

        h2 {
            font-size: 42px;
            color: #3B0270;
            margin-bottom: 10px;
        }

        p {
            font-size: 20px;
            color: #555;
            margin-bottom: 25px;
        }

        button {
            width: 50%;
            padding: 12px;
            background-color: #F4991A;
            border: none;
            border-radius: 15px;
            color: white;
            font-size: 18px;
            cursor: pointer;
            transition: 0.3s ease;
            font-weight: bold;
        }

        button:hover {
            background-color: #c97205;
        }

        a {
            text-decoration: none;
        }
    </style>
</head>

<body>

    <div class="containerWelcome">
        <img src="../images/logo.png" alt="Company Logo">

        <div class="container">
            <h2>Welcome</h2>
            <p>Your hassle-free attendance solution</p>
            <a href="login.php"><button>START</button></a>
        </div>
    </div>

</body>
</html>

