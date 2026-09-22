<?php require_once dirname(__DIR__).'/core/bootstrap.php'; if(is_admin())redirect('/admin/'); $gateHash=setting($pdo,'admin_gate_hash',''); if(isset($_GET['gate']) && is_string($_GET['gate']) && $gateHash && password_verify($_GET['gate'],$gateHash)){$_SESSION['admin_gate_ok']=1; redirect('/admin/login.php');} if(empty($_SESSION['admin_gate_ok'])){http_response_code(404);exit('Not Found');} $error=''; if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$ip=client_ip();if(login_rate_limited($pdo,$ip)){$error='تلاش‌های ناموفق زیاد بوده؛ ۱۵ دقیقه بعد دوباره امتحان کنید.';}else{$u=trim($_POST['username']??'');$p=(string)($_POST['password']??'');$s=$pdo->prepare('SELECT * FROM admins WHERE username=? LIMIT 1');$s->execute([$u]);$a=$s->fetch();if($a&&password_verify($p,$a['password_hash'])){clear_login_failures($pdo,$ip);session_regenerate_id(true);$_SESSION['admin_id']=$a['id'];$pdo->prepare('UPDATE admins SET last_login_at=NOW() WHERE id=?')->execute([$a['id']]);redirect('/admin/');}record_login_failure($pdo,$ip);$error='نام کاربری یا رمز اشتباه است.';}}?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>ورود مدیر</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap');
*{box-sizing:border-box}
:root{--accent:#7c96ff;--accent2:#a06bff;--pink:#ff6bd6;--bad:#ff5d78;--good:#2fe3a3}
html,body{height:100%}
body{
  font-family:'Vazirmatn',Tahoma,sans-serif;color:#fff;margin:0;
  display:grid;place-items:center;position:relative;overflow:hidden;
  background:
    radial-gradient(900px 600px at 12% 8%,rgba(124,150,255,.28),transparent 60%),
    radial-gradient(800px 600px at 92% 92%,rgba(160,107,255,.22),transparent 55%),
    radial-gradient(600px 500px at 50% 50%,rgba(78,224,200,.08),transparent 60%),
    linear-gradient(180deg,#0d1326,#0a0e1a 45%);
}
/* network / node grid backdrop */
body::before{
  content:"";position:absolute;inset:0;z-index:0;opacity:.5;
  background-image:radial-gradient(rgba(124,150,255,.35) 1px,transparent 1.5px);
  background-size:34px 34px;
  -webkit-mask-image:radial-gradient(700px 500px at 50% 40%,#000,transparent 75%);
          mask-image:radial-gradient(700px 500px at 50% 40%,#000,transparent 75%);
}
.blob{position:absolute;border-radius:50%;filter:blur(70px);opacity:.55;z-index:0}
.blob.b1{width:360px;height:360px;background:#7c96ff;top:-130px;right:-110px;animation:float1 9s ease-in-out infinite}
.blob.b2{width:320px;height:320px;background:#ff6bd6;bottom:-110px;left:-100px;animation:float2 11s ease-in-out infinite}
.blob.b3{width:220px;height:220px;background:#4ee0c8;bottom:20%;right:8%;animation:float1 13s ease-in-out infinite}
@keyframes float1{0%,100%{transform:translateY(0)}50%{transform:translateY(28px)}}
@keyframes float2{0%,100%{transform:translateY(0)}50%{transform:translateY(-28px)}}

.wrap{position:relative;z-index:1;width:min(410px,92%)}

/* rotating gradient border frame */
.frame{position:relative;border-radius:26px;padding:1.5px;
  background:conic-gradient(from var(--a,0deg),var(--accent),var(--pink),var(--accent2),var(--accent));
  animation:spin 6s linear infinite;
}
@keyframes spin{to{--a:360deg}}
@property --a{syntax:'<angle>';inherits:false;initial-value:0deg}

.c{
  position:relative;background:rgba(17,23,44,.86);backdrop-filter:blur(22px);
  padding:38px 32px 32px;border-radius:24.5px;
  box-shadow:0 30px 70px -20px rgba(0,0,0,.65),inset 0 0 0 1px rgba(255,255,255,.04);
}

.logo-ring{width:64px;height:64px;margin:0 auto 16px;position:relative;display:grid;place-items:center}
.logo-ring::before{content:"";position:absolute;inset:-6px;border-radius:20px;
  background:linear-gradient(135deg,var(--accent),var(--accent2),var(--pink));
  filter:blur(10px);opacity:.55;animation:pulseGlow 2.4s ease-in-out infinite}
@keyframes pulseGlow{0%,100%{opacity:.45;transform:scale(1)}50%{opacity:.8;transform:scale(1.08)}}
.logo{position:relative;width:64px;height:64px;border-radius:18px;
  background:linear-gradient(135deg,var(--accent),var(--accent2) 60%,var(--pink));
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 10px 28px -6px rgba(124,150,255,.6);}
.logo svg{width:30px;height:30px}

h2{text-align:center;margin:0 0 4px;font-size:21px;font-weight:800;letter-spacing:.2px}
.sub{text-align:center;color:#8b9ac2;font-size:12.5px;margin:0 0 26px;font-weight:600}
.sub::before{content:"● ";color:var(--good);font-size:9px;vertical-align:middle}

label{display:block;font-size:12px;font-weight:700;color:#8b9ac2;margin:14px 0 7px}

.field{position:relative}
.field svg{
  position:absolute;top:50%;right:14px;transform:translateY(-50%);
  width:18px;height:18px;color:#6c7aa8;pointer-events:none;transition:color .15s ease;
}
.field input{padding-right:42px}
.field:focus-within svg{color:var(--accent)}

input{
  width:100%;box-sizing:border-box;padding:13px 14px;border-radius:12px;
  border:1px solid #2c3a5e;background:rgba(9,14,28,.75);color:#fff;
  font-family:inherit;font-size:14px;transition:.15s;
}
input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 4px rgba(124,150,255,.18);background:rgba(9,14,28,.95)}

.toggle-pass{
  position:absolute;top:50%;left:12px;transform:translateY(-50%);
  background:none;border:0;padding:4px;cursor:pointer;color:#6c7aa8;
  box-shadow:none;width:auto;margin:0;display:flex;
}
.toggle-pass:hover{color:#cfd8ff;transform:translateY(-50%);filter:none}
.toggle-pass svg{width:18px;height:18px}

button.submit{
  width:100%;margin-top:22px;padding:14px;border:0;border-radius:12px;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  color:#08101f;font-weight:800;font-size:14.5px;cursor:pointer;
  box-shadow:0 10px 26px -8px rgba(124,150,255,.6);transition:.15s;
  display:flex;align-items:center;justify-content:center;gap:8px;
}
button.submit:hover{transform:translateY(-2px);filter:brightness(1.08);box-shadow:0 14px 32px -8px rgba(124,150,255,.7)}
button.submit:active{transform:translateY(0)}

.e{
  display:flex;align-items:center;gap:8px;
  background:rgba(255,93,120,.12);border-inline-start:4px solid var(--bad);
  color:#ffc1cd;padding:12px 14px;border-radius:10px;font-size:13px;margin:0 0 8px;
  animation:shake .35s ease;
}
.e svg{width:16px;height:16px;flex:0 0 auto}
@keyframes shake{10%,90%{transform:translateX(-1px)}20%,80%{transform:translateX(2px)}30%,50%,70%{transform:translateX(-4px)}40%,60%{transform:translateX(4px)}}

.foot{text-align:center;margin-top:20px;color:#5c6a90;font-size:11.5px;font-weight:600}
</style>
</head>
<body>
<span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span>
<div class="wrap">
  <div class="frame">
    <div class="c">
      <div class="logo-ring">
        <div class="logo">
          <svg viewBox="0 0 24 24" fill="none" stroke="#08101f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V5l-8-3z"/><path d="m9.5 12 2 2 3.5-3.5"/></svg>
        </div>
      </div>
      <h2>ورود مدیر</h2>
      <p class="sub">پنل مدیریت VPN</p>
      <?php if($error):?>
      <p class="e"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg><?=e($error)?></p>
      <?php endif?>
      <form method="post">
        <?=csrf_field()?>
        <label>نام کاربری</label>
        <div class="field">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
          <input name="username" autocomplete="username" required autofocus>
        </div>
        <label>رمز عبور</label>
        <div class="field">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input id="pw" name="password" type="password" autocomplete="current-password" required style="padding-left:42px">
          <button type="button" class="toggle-pass" id="pwToggle" aria-label="نمایش رمز">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <button class="submit" type="submit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
          ورود به پنل
        </button>
      </form>
      <div class="foot">دسترسی محدود · فقط برای مدیران مجاز</div>
    </div>
  </div>
</div>
<script>
document.getElementById('pwToggle')?.addEventListener('click',function(){
  var pw=document.getElementById('pw');
  var showing=pw.type==='text';
  pw.type=showing?'password':'text';
  this.style.color=showing?'#6c7aa8':'#a3b4ff';
});
</script>
</body>
</html>
