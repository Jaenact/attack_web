<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <title>CTF 문제 목록</title>
  <style>
    body {
      background-color: #121212;
      color: #e0e0e0;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 40px;
      animation: fadeIn 1s ease-in;
    }

    h1 {
      font-size: 32px;
      margin-bottom: 30px;
      color: #00ffcc;
      text-shadow: 0 0 5px #00ffcc88;
    }

    button {
      padding: 10px 20px;
      font-size: 16px;
      background: #00ffcc;
      color: #000;
      border: none;
      cursor: pointer;
      border-radius: 5px;
      transition: all 0.3s ease;
    }

    button:hover {
      background: #00ddb3;
      transform: scale(1.05);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      animation: fadeIn 1.5s ease-in;
    }

    th, td {
      padding: 15px;
      border-bottom: 1px solid #333;
      text-align: left;
    }

    th {
      color: #00ffcc;
    }

    tr:hover {
      background-color: #1e1e1e;
    }

    a {
      color: #66ccff;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }

    /* 모달 */
    #formModal {
      display: none;
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: #1c1c1c;
      padding: 30px;
      border: 2px solid #00ffcc;
      box-shadow: 0 0 20px #00ffcc88;
      z-index: 1000;
      border-radius: 8px;
      animation: fadeIn 0.3s ease;
    }

    #formModal h3 {
      margin-bottom: 15px;
      color: #00ffcc;
    }

    #formModal input, #formModal select {
      width: 100%;
      padding: 10px;
      margin-bottom: 15px;
      background: #2a2a2a;
      border: 1px solid #444;
      color: #fff;
      border-radius: 5px;
    }

    #overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background-color: rgba(0,0,0,0.6);
      z-index: 999;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <h1>CTF 문제 목록</h1>

  <button onclick="showForm()">문제 추가</button>

  <table id="problemTable">
    <thead>
      <tr>
        <th>문제 이름</th>
        <th>작성자</th>
        <th>난이도</th>
        <th>문제 URL</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Hex Only</td>
        <td>jaehyun</td>
        <td>하</td>
        <td><a href="http://210.102.178.92:9999/wargame/hexonly/hexonly.php" target="_blank">문제 보기</a></td>
      </tr>
    </tbody>
  </table>
</body>
</html>
