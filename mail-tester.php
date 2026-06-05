<?php
// mail-tester.php — standalone email tester opened from the top-bar "Email" button.
require_once __DIR__ . '/auth.php';
// Only logged-in staff may open the mail tester.
requireRole(['admin', 'teacher', 'principal', 'superadmin'], 'index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Indiegram Mail Tester</title>

  <style>
    *{
      margin:0;
      padding:0;
      box-sizing:border-box;
      font-family: Arial, sans-serif;
    }

    body{
      background:#0f172a;
      min-height:100vh;
      display:flex;
      justify-content:center;
      align-items:center;
      padding:20px;
    }

    .card{
      width:100%;
      max-width:520px;
      background:#111827;
      padding:30px;
      border-radius:18px;
      box-shadow:0 10px 30px rgba(0,0,0,0.4);
      color:white;
    }

    h1{
      text-align:center;
      margin-bottom:25px;
      color:#38bdf8;
    }

    .group{
      margin-bottom:18px;
    }

    label{
      display:block;
      margin-bottom:8px;
      font-size:14px;
    }

    input,
    textarea{
      width:100%;
      padding:14px;
      border:none;
      outline:none;
      border-radius:10px;
      background:#1f2937;
      color:white;
      font-size:15px;
    }

    textarea{
      resize:none;
      height:160px;
    }

    button{
      width:100%;
      padding:14px;
      border:none;
      border-radius:10px;
      background:#38bdf8;
      color:black;
      font-weight:bold;
      font-size:16px;
      cursor:pointer;
      transition:0.3s;
    }

    button:hover{
      background:#0ea5e9;
    }

    .status{
      margin-top:18px;
      text-align:center;
      font-size:14px;
    }

    .success{
      color:#22c55e;
    }

    .error{
      color:#ef4444;
    }
  </style>
</head>
<body>

<div class="card">

  <h1>Indiegram Mail Tester</h1>

  <form id="mailForm">

    <div class="group">
      <label>To Email</label>
      <input 
        type="email" 
        name="to" 
        placeholder="example@gmail.com"
        required
      >
    </div>

    <div class="group">
      <label>Subject</label>
      <input 
        type="text" 
        name="subject"
        placeholder="Testing Mail"
        required
      >
    </div>

    <div class="group">
      <label>Message</label>
      <textarea 
        name="body"
        placeholder="Write your message..."
        required
      ></textarea>
    </div>

    <!-- Hidden Token -->
    <input 
      type="hidden" 
      name="token" 
      value="MY_SECRET_KEY_123"
    >

    <button type="submit">
      Send Email
    </button>

    <div class="status" id="status"></div>

  </form>

</div>

<script>

const form = document.getElementById("mailForm");
const statusBox = document.getElementById("status");

form.addEventListener("submit", async (e) => {

  e.preventDefault();

  statusBox.className = "status";
  statusBox.innerHTML = "Sending email...";

  const formData = new FormData(form);

  try {

    const response = await fetch(
      "https://email.indiegrampublications.com/send_email.php",
      {
        method: "POST",
        body: formData,
        headers: {
          "Authorization": "MY_SECRET_KEY_123"
        }
      }
    );

    const result = await response.text();

    console.log(result);

    statusBox.className = "status success";
    statusBox.innerHTML = "✅ Email Sent Successfully";

    form.reset();

  } catch (error) {

    console.error(error);

    statusBox.className = "status error";
    statusBox.innerHTML = "❌ Failed To Send Email";

  }

});

</script>

</body>
</html>
