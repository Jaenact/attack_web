<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title><?= $title ?? 'PLC 시스템' ?></title>
  <meta name="viewport" content="width=1280">
  <link rel="stylesheet" href="/assets/css/main.css">
  <link href="https://fonts.googleapis.com/css?family=Noto+Sans+KR:400,700&display=swap" rel="stylesheet">
</head>
<body>
  <a href="#main-content" class="skip-link">본문 바로가기</a>
  <header class="header" role="banner">
    <a href="/public/index.php" class="logo" aria-label="홈으로">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><rect width="24" height="24" rx="6" fill="#fff" fill-opacity="0.18"/><path fill="#003366" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/></svg>
      PLC Rotator System
    </a>
    <nav class="main-nav" aria-label="주요 메뉴">
      <ul>
        <li><a href="/public/index.php"<?= ($active ?? '')==='dashboard' ? ' aria-current="page"' : '' ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z" fill="#fff"/></svg>대시보드</a></li>
        <li><a href="/public/control.php"<?= ($active ?? '')==='control' ? ' aria-current="page"' : '' ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 17.93V20h-2v-.07A8.001 8.001 0 014.07 13H4v-2h.07A8.001 8.001 0 0111 4.07V4h2v.07A8.001 8.001 0 0119.93 11H20v2h-.07A8.001 8.001 0 0113 19.93z" fill="#fff"/></svg>제어</a></li>
        <li><a href="/public/faults.php"<?= ($active ?? '')==='faults' ? ' aria-current="page"' : '' ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2V7h2v8z" fill="#fff"/></svg>고장</a></li>
        <?php if (isset($_SESSION['admin'])): ?>
        <li><a href="/public/logs.php"<?= ($active ?? '')==='logs' ? ' aria-current="page"' : '' ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 6v18h18V6H3zm16 16H5V8h14v14zm-7-2h2v-2h-2v2zm0-4h2v-4h-2v4z" fill="#fff"/></svg>로그</a></li>
        <?php endif; ?>
        <li><a href="/public/logout.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M16 13v-2H7V8l-5 4 5 4v-3h9zm3-10H5c-1.1 0-2 .9-2 2v6h2V5h14v14H5v-6H3v6c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" fill="#fff"/></svg>로그아웃</a></li>
        <li><button id="profileBtn" class="profile-btn nav-profile" style="background:none;border:none;color:#fff;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:8px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 12c2.7 0 8 1.34 8 4v2H4v-2c0-2.66 5.3-4 8-4zm0-2a4 4 0 100-8 4 4 0 000 8z" fill="#fff"/></svg>
          <span style="display:inline-block;vertical-align:middle;">내 정보</span>
        </button></li>
      </ul>
    </nav>
  </header>
  <main id="main-content" class="container" tabindex="-1">
    <?php echo $content; ?>
  </main>
  <footer class="footer" role="contentinfo">
    <span>© <?= date('Y') ?> PLC Rotator System. All rights reserved.</span>
  </footer>
  <!-- 내 정보/프로필 모달 -->
  <div id="profileModal" class="modal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.25);justify-content:center;align-items:center;">
    <div class="modal-content card" style="min-width:320px;max-width:400px;position:relative;">
      <button onclick="closeProfileModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:#888;">&times;</button>
      <div style="display:flex;gap:12px;margin-bottom:18px;">
        <button id="tabProfile" class="tab-btn active" style="flex:1;font-weight:600;padding:8px 0;border:none;border-radius:6px 6px 0 0;cursor:pointer;">👤 내 정보</button>
        <button id="tabPw" class="tab-btn inactive" style="flex:1;font-weight:600;padding:8px 0;border:none;border-radius:6px 6px 0 0;cursor:pointer;">🔒 비밀번호 변경</button>
      </div>
      <div id="profileTab">
        <form id="profileForm" method="post" action="/src/user/update_profile.php" enctype="multipart/form-data">
          <label>이름</label>
          <input type="text" name="name" id="profileName" class="input" style="width:100%;margin-bottom:10px;">
          <label>연락처</label>
          <input type="text" name="phone" id="profilePhone" class="input" style="width:100%;margin-bottom:10px;">
          <label>프로필 사진</label>
          <input type="file" name="profile_img" accept="image/*" class="input" style="width:100%;margin-bottom:10px;">
          <div id="profileImgPreview" style="margin-bottom:10px;"></div>
          <button type="submit" class="btn-primary" style="width:100%;margin-top:10px;">저장</button>
        </form>
      </div>
      <div id="pwTab" style="display:none;">
        <form id="pwForm" method="post" action="/src/user/update_profile.php">
          <label>현재 비밀번호</label>
          <input type="password" name="current_pw" id="currentPw" class="input" style="width:100%;margin-bottom:10px;" required>
          <label>새 비밀번호</label>
          <input type="password" name="new_pw" id="newPw" class="input" style="width:100%;margin-bottom:10px;" required>
          <label>새 비밀번호 확인</label>
          <input type="password" name="new_pw2" id="newPw2" class="input" style="width:100%;margin-bottom:10px;" required>
          <button type="submit" class="btn-primary" style="width:100%;margin-top:10px;">비밀번호 변경</button>
          <div id="pwMsg" style="margin-top:10px;font-size:14px;"></div>
        </form>
      </div>
    </div>
  </div>
  <script>
    // 탭 전환
    function setTab(active) {
      if (active === 'profile') {
        tabProfile.classList.add('active'); tabProfile.classList.remove('inactive');
        tabPw.classList.remove('active'); tabPw.classList.add('inactive');
        profileTab.style.display = 'block'; pwTab.style.display = 'none';
      } else {
        tabProfile.classList.remove('active'); tabProfile.classList.add('inactive');
        tabPw.classList.add('active'); tabPw.classList.remove('inactive');
        profileTab.style.display = 'none'; pwTab.style.display = 'block';
      }
    }
    const tabProfile = document.getElementById('tabProfile');
    const tabPw = document.getElementById('tabPw');
    const profileTab = document.getElementById('profileTab');
    const pwTab = document.getElementById('pwTab');
    tabProfile.onclick = function() { setTab('profile'); };
    tabPw.onclick = function() { setTab('pw'); };
    setTab('profile');
    // 내 정보 불러오기
    document.getElementById('profileBtn').onclick = function() {
      document.getElementById('profileModal').style.display = 'flex';
      fetch('/src/user/update_profile.php?get=1')
        .then(res => res.json())
        .then(data => {
          document.getElementById('profileName').value = data.name || '';
          document.getElementById('profilePhone').value = data.phone || '';
          if (data.profile_img) {
            document.getElementById('profileImgPreview').innerHTML = '<img src="/uploads/profile/' + data.profile_img + '" alt="프로필" style="max-width:100px;max-height:100px;border-radius:50%;">';
          } else {
            document.getElementById('profileImgPreview').innerHTML = '';
          }
        });
    };
    // 비밀번호 변경 ajax
    const pwForm = document.getElementById('pwForm');
    pwForm.onsubmit = function(e) {
      e.preventDefault();
      const cur = document.getElementById('currentPw').value;
      const pw1 = document.getElementById('newPw').value;
      const pw2 = document.getElementById('newPw2').value;
      const msg = document.getElementById('pwMsg');
      msg.textContent = '';
      if (pw1 !== pw2) {
        msg.textContent = '새 비밀번호가 일치하지 않습니다.';
        msg.style.color = 'red';
        return;
      }
      fetch('/src/user/update_profile.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({change_pw:1, current_pw:cur, new_pw:pw1})
      })
      .then(res => res.json())
      .then(data => {
        msg.textContent = data.msg;
        msg.style.color = data.success ? 'green' : 'red';
        if (data.success) {
          pwForm.reset();
        }
      })
      .catch(()=>{msg.textContent='오류가 발생했습니다.';msg.style.color='red';});
    };
    function closeProfileModal() {
      document.getElementById('profileModal').style.display = 'none';
    }
  </script>
</body>
</html> 