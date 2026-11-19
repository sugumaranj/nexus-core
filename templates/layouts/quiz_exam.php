<?php
/**
 * NexusCore Ultra-Low-Bandwidth Exam Runner Shell (< 7 KB gzipped)
 * Zero external frameworks, zero CDNs, zero images, zero camera.
 * Engineered to operate smoothly on 1-3 KB/s (1-3 kbps) links with
 * instant localStorage persistence, micro-delta batched sync (~40B),
 * fullscreen lockdown, tab-switch/blur detection, copy/paste blocking,
 * and anti-capture CSS student ID watermark.
 */
declare(strict_types=1);

$bootJson = json_encode($bootstrap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
$syncUrl  = base_url('/student/prelims/sync');
$subUrl   = base_url('/student/prelims/submit');
$doneUrl  = base_url('/student/prelims/submitted?quiz_id=' . (int)$bootstrap['qid']);
$wmText   = htmlspecialchars(trim($studentReg . ' • ' . $studentName), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars((string)$bootstrap['ttl'], ENT_QUOTES, 'UTF-8') ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#e2e8f0;user-select:none;-webkit-user-select:none;overflow-x:hidden}
#wm{position:fixed;inset:0;pointer-events:none;z-index:5;overflow:hidden;opacity:.055;display:grid;grid-template-columns:repeat(3,1fr);gap:90px;padding:40px;font-size:15px;font-weight:700;color:#fff}
#wm span{transform:rotate(-28deg);white-space:nowrap}
.hdr{position:sticky;top:0;z-index:20;background:#1e293b;border-bottom:1px solid #334155;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.title{font-weight:700;font-size:15px;color:#f8fafc}
.meta{font-size:12px;color:#94a3b8}
.pill{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600}
.p-ok{background:#064e3b;color:#6ee7b7}.p-warn{background:#78350f;color:#fcd34d}.p-bad{background:#7f1d1d;color:#fca5a5}
.tmr{font-family:ui-monospace,Consolas,monospace;font-size:18px;font-weight:800;padding:5px 12px;border-radius:8px;background:#0f172a;border:1px solid #38bdf8;color:#38bdf8}
.tmr.low{border-color:#ef4444;color:#fca5a5;animation:bk 1s infinite}
@keyframes bk{50%{opacity:.55}}
.wrap{position:relative;z-index:10;max-width:1180px;margin:16px auto;padding:0 14px;display:grid;grid-template-columns:1fr 270px;gap:16px}
@media(max-width:860px){.wrap{grid-template-columns:1fr}}
.card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:18px}
.qhdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;font-size:13px;color:#94a3b8}
.qtxt{font-size:16px;line-height:1.55;color:#f8fafc;margin-bottom:16px;white-space:pre-wrap}
.opts{display:flex;flex-direction:column;gap:10px}
.opt{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border:1px solid #334155;border-radius:10px;background:#0f172a;cursor:pointer;transition:border-color .12s,background .12s}
.opt:hover{border-color:#60a5fa}
.opt.sel{border-color:#38bdf8;background:#0c4a6e}
.olbl{min-width:26px;height:26px;border-radius:6px;background:#1e293b;border:1px solid #475569;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px}
.opt.sel .olbl{background:#38bdf8;color:#0f172a;border-color:#38bdf8}
.acts{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;margin-top:18px;padding-top:14px;border-top:1px solid #334155}
.btn{border:0;border-radius:8px;padding:9px 14px;font-size:13px;font-weight:600;cursor:pointer}
.b-pri{background:#2563eb;color:#fff}.b-sec{background:#334155;color:#e2e8f0}.b-wrn{background:#d97706;color:#fff}.b-red{background:#dc2626;color:#fff}.b-grn{background:#16a34a;color:#fff}
.btn:disabled{opacity:.45;cursor:not-allowed}
.pal{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin-top:10px}
.pb{height:34px;border-radius:7px;border:1px solid #475569;background:#0f172a;color:#cbd5e1;font-weight:700;font-size:12px;cursor:pointer}
.pb.cur{outline:2px solid #38bdf8;outline-offset:1px}
.pb.ans{background:#15803d;border-color:#22c55e;color:#fff}
.pb.rev{background:#b45309;border-color:#f59e0b;color:#fff}
.leg{display:flex;flex-wrap:wrap;gap:8px;font-size:11px;color:#94a3b8;margin-top:12px}
.dot{width:10px;height:10px;border-radius:3px;display:inline-block;margin-right:4px}
.ov{position:fixed;inset:0;z-index:999;background:rgba(2,6,23,.94);display:none;align-items:center;justify-content:center;padding:20px}
.ov.show{display:flex}
.mdl{background:#1e293b;border:1px solid #475569;border-radius:14px;max-width:460px;width:100%;padding:22px;text-align:center}
.mdl h3{margin-bottom:8px;color:#f8fafc}.mdl p{font-size:14px;color:#cbd5e1;line-height:1.5;margin-bottom:16px}
.blur #mainApp{filter:blur(14px);pointer-events:none}
</style>
</head>
<body class="blur">
<div id="wm"><?php for($i=0;$i<15;$i++) echo "<span>{$wmText}</span>"; ?></div>

<div id="mainApp">
  <header class="hdr">
    <div>
      <div class="title"><?= htmlspecialchars((string)$bootstrap['ttl'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="meta"><?= $wmText ?></div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <span id="netPill" class="pill p-ok">Online · Saved</span>
      <span id="vioPill" class="pill p-ok">Violations: 0/<?= (int)$bootstrap['mv'] ?></span>
      <div id="tmr" class="tmr">--:--</div>
      <button type="button" class="btn b-red" id="btnOpenSubmit">Submit &amp; Exit</button>
    </div>
  </header>

  <main class="wrap">
    <section class="card">
      <div class="qhdr">
        <strong id="qNum">Question 1</strong>
        <span id="qStat">Unanswered</span>
      </div>
      <div id="qTxt" class="qtxt"></div>
      <div id="opts" class="opts"></div>
      <div class="acts">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button type="button" class="btn b-sec" id="btnPrev">&larr; Prev</button>
          <button type="button" class="btn b-sec" id="btnClr">Clear</button>
          <button type="button" class="btn b-wrn" id="btnRev">Mark Review &amp; Next</button>
        </div>
        <button type="button" class="btn b-pri" id="btnNext">Save &amp; Next &rarr;</button>
      </div>
    </section>

    <aside class="card">
      <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:700">
        <span>Question Palette</span>
        <span id="sumCnt">0/0</span>
      </div>
      <div id="pal" class="pal"></div>
      <div class="leg">
        <span><i class="dot" style="background:#15803d"></i>Answered</span>
        <span><i class="dot" style="background:#b45309"></i>Review</span>
        <span><i class="dot" style="background:#0f172a;border:1px solid #475569"></i>Pending</span>
      </div>
      <div style="margin-top:14px;font-size:11px;color:#94a3b8;line-height:1.45">
        Keys: <b>1-4</b> Select · <b>N/P</b> Next/Prev · <b>M</b> Review.<br>
        Answers save locally first and sync automatically even on 1–3 kbps networks.
      </div>
    </aside>
  </main>
</div>

<div id="fsOv" class="ov show">
  <div class="mdl">
    <h3 id="fsTitle">Fullscreen Exam Mode Required</h3>
    <p id="fsMsg">Click below to enter locked fullscreen mode and view your questions. Fullscreen shortcuts (Esc, F11, Alt+Tab, Ctrl+W) are blocked and violations are recorded.</p>
    <button type="button" class="btn b-pri" id="btnEnterFs" style="width:100%;padding:12px">Enter Fullscreen &amp; Continue</button>
    <button type="button" class="btn b-red" id="btnFsSubmit" style="width:100%;padding:10px;margin-top:10px;display:none">Want to Exit? Submit &amp; Close Test</button>
  </div>
</div>

<div id="vioOv" class="ov">
  <div class="mdl" style="border-color:#ef4444">
    <h3 style="color:#fca5a5" id="vioTitle">&#9888; Exam Security Violation</h3>
    <p id="vioMsg"></p>
    <div style="display:flex;flex-direction:column;gap:8px">
      <button type="button" class="btn b-pri" id="btnVioOk" style="width:100%;padding:11px">Acknowledge &amp; Return to Fullscreen</button>
      <button type="button" class="btn b-sec" id="btnVioSub" style="width:100%;padding:9px">Want to Leave? Submit &amp; Close Test</button>
    </div>
  </div>
</div>

<div id="subOv" class="ov">
  <div class="mdl" style="border-color:#22c55e">
    <h3>Confirm Submit &amp; Close Test</h3>
    <p id="subMsg"></p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
      <button type="button" class="btn b-sec" id="btnCancelSub">Return to Test</button>
      <button type="button" class="btn b-grn" id="btnConfirmSub">Yes, Submit &amp; Close Test</button>
    </div>
  </div>
</div>

<div id="waitNetOv" class="ov">
  <div class="mdl" style="border-color:#38bdf8">
    <h3 id="waitNetTitle" style="color:#38bdf8">Answers Locked &amp; Saved Offline</h3>
    <p id="waitNetMsg">Your answers have been locked safely in your browser. Waiting for internet connection to upload your final submission automatically. Do not clear browser data.</p>
    <div id="waitNetStat" class="pill p-warn" style="margin-bottom:14px">Offline · Waiting for network...</div>
    <button type="button" class="btn b-pri" id="btnForceUpload" style="width:100%;padding:11px">Sync &amp; Submit Now</button>
  </div>
</div>

<script>
(function(){
'use strict';
var B=<?= $bootJson ?>;
var CSRF=<?= json_encode($csrfToken) ?>;
var SYNC_URL=<?= json_encode($syncUrl) ?>;
var SUB_URL=<?= json_encode($subUrl) ?>;
var DONE_URL=<?= json_encode($doneUrl) ?>;
var LS_KEY='nx_prelims_a_'+B.aid;
var PEND_KEY='nx_prelims_pending_'+B.qid;
var LBLS=['A','B','C','D','E','F'];
var clockOffset=(Number(B.srv||0)>0 ? (Number(B.srv)-Math.floor(Date.now()/1000)) : 0);

function serverNowIso(){
  var d=new Date((Math.floor(Date.now()/1000)+clockOffset)*1000);
  return d.toISOString();
}

// State hydrated from server + localStorage recovery (low-bandwidth offline resilience)
var Q=B.q||[], idx=0, rem=Number(B.rem||0), vc=Number(B.vc||0), mv=Number(B.mv||3), vas=Number(B.vas||1), reqFs=Number(B.fs||1);
var ans={}, rev={}, dirty={}, vioQ=[], syncing=false, submitted=false, locked=false, uploadingPending=false, backoff=1000, startedFs=false, lastVioTs=0;

Q.forEach(function(item){ if(item.s) ans[item.d]=item.s; if(item.m) rev[item.d]=1; });
try{
  var saved=JSON.parse(localStorage.getItem(LS_KEY)||'null');
  if(saved && saved.aid===B.aid){
    Object.keys(saved.ans||{}).forEach(function(k){ if(ans[k]!==saved.ans[k]){ ans[k]=saved.ans[k]; dirty[k]=1; }});
    Object.keys(saved.rev||{}).forEach(function(k){ if(rev[k]!==saved.rev[k]){ rev[k]=saved.rev[k]; dirty[k]=1; }});
    if(Array.isArray(saved.vioQ) && saved.vioQ.length){ vioQ=saved.vioQ; }
  }
}catch(e){}

function saveLocal(){
  try{ localStorage.setItem(LS_KEY, JSON.stringify({aid:B.aid,ans:ans,rev:rev,vioQ:vioQ,ts:Date.now()})); }catch(e){}
}

var $=function(id){return document.getElementById(id);};

function setNet(cls,txt){ var p=$('netPill'); p.className='pill '+cls; p.textContent=txt; }
function updateVioPill(){
  var p=$('vioPill');
  p.textContent='Violations: '+vc+(vas?('/'+mv):'');
  p.className='pill '+(vc===0?'p-ok':(vc>=mv-1?'p-bad':'p-warn'));
}

function render(){
  if(!Q.length) return;
  var cur=Q[idx], d=cur.d;
  $('qNum').textContent='Question '+(idx+1)+' of '+Q.length;
  $('qStat').textContent=rev[d]?'Marked for Review':(ans[d]?'Answered':'Unanswered');
  $('qTxt').textContent=cur.t;

  var oBox=$('opts'); oBox.innerHTML='';
  cur.o.forEach(function(pair,i){
    var oid=pair[0], txt=pair[1], div=document.createElement('div');
    div.className='opt'+(ans[d]===oid?' sel':'');
    div.innerHTML='<span class="olbl">'+(LBLS[i]||i+1)+'</span><span style="flex:1"></span>';
    div.lastChild.textContent=txt;
    div.onclick=function(){ ans[d]=oid; delete rev[d]; dirty[d]=1; saveLocal(); render(); scheduleSync(false); };
    oBox.appendChild(div);
  });

  $('btnPrev').disabled=(idx===0);
  $('btnNext').textContent=(idx===Q.length-1)?'Save':'Save & Next →';
  $('btnRev').textContent=rev[d]?'Unmark Review':'Mark Review & Next';

  // Palette
  var pal=$('pal'), aCnt=0; pal.innerHTML='';
  Q.forEach(function(it,i){
    if(ans[it.d]) aCnt++;
    var b=document.createElement('button'); b.type='button';
    b.className='pb'+(i===idx?' cur':'')+(rev[it.d]?' rev':(ans[it.d]?' ans':''));
    b.textContent=String(i+1);
    b.onclick=function(){ idx=i; render(); };
    pal.appendChild(b);
  });
  $('sumCnt').textContent=aCnt+'/'+Q.length+' Ans';
}

function fmtTime(s){
  s=Math.max(0,Math.floor(s));
  var m=Math.floor(s/60), r=s%60;
  return (m<10?'0':'')+m+':'+(r<10?'0':'')+r;
}

function tick(){
  if(submitted) return;
  rem--;
  var el=$('tmr'); el.textContent=fmtTime(rem);
  el.className='tmr'+(rem<=60?' low':'');
  if(rem<=0){ doFinalSubmit('Auto_Submitted_Time'); }
}

var syncTimer=null;
function scheduleSync(immediate){
  if(submitted) return;
  if(immediate){ flushSync(); return; }
  if(Object.keys(dirty).length>=3){ flushSync(); return; }
  if(!syncTimer){ syncTimer=setTimeout(function(){ syncTimer=null; flushSync(); }, 6000); }
}

function buildCompactArray(onlyDirty){
  var out=[];
  Q.forEach(function(it){
    var d=it.d;
    if(!onlyDirty || dirty[d]){
      out.push([d, ans[d]||null, rev[d]?1:0]);
    }
  });
  return out;
}

async function flushSync(){
  if(syncing || submitted) return;
  var delta=buildCompactArray(true);
  var vBatch=vioQ.splice(0, vioQ.length);
  if(!delta.length && !vBatch.length && navigator.onLine===false) return;
  syncing=true;
  setNet('p-warn','Syncing...');
  try{
    var res=await fetch(SYNC_URL,{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body:JSON.stringify({c:CSRF,aid:B.aid,qid:B.qid,a:delta,v:vBatch})
    });
    var j=await res.json();
    if(j && j.ok){
      delta.forEach(function(row){ delete dirty[row[0]]; });
      if(typeof j.rem==='number') rem=j.rem;
      if(typeof j.vc==='number'){ vc=j.vc; updateVioPill(); }
      backoff=1000;
      setNet('p-ok','Online · Saved');
      if(j.s && j.s!=='In_Progress'){
        submitted=true;
        try{ localStorage.removeItem(LS_KEY); }catch(e){}
        window.location.href=DONE_URL;
        return;
      }
    } else if(j && j.code==='ALREADY_FINALIZED'){
      submitted=true; window.location.href=DONE_URL; return;
    } else {
      throw new Error((j&&j.msg)||'Sync rejected');
    }
  }catch(e){
    vioQ=vBatch.concat(vioQ);
    setNet('p-warn','Slow/Offline · Saved Locally ('+Object.keys(dirty).length+')');
    backoff=Math.min(30000, backoff*2);
    setTimeout(flushSync, backoff);
  }finally{
    syncing=false;
  }
}

var pendingRetryTimer=null;
function showWaitNetOverlay(statusText, isWarn){
  document.body.classList.add('blur');
  $('fsOv').classList.remove('show');
  $('vioOv').classList.remove('show');
  $('subOv').classList.remove('show');
  $('waitNetOv').classList.add('show');
  var st=$('waitNetStat');
  st.textContent=statusText;
  st.className='pill '+(isWarn?'p-warn':'p-ok');
}

async function uploadPendingSubmit(){
  if(uploadingPending) return;
  var raw=null;
  try{ raw=localStorage.getItem(PEND_KEY); }catch(e){}
  if(!raw) return;

  var bundle=null;
  try{ bundle=JSON.parse(raw); }catch(e){ return; }
  if(!bundle || Number(bundle.aid)!==Number(B.aid)) return;

  if(navigator.onLine===false){
    showWaitNetOverlay('Offline · Saved locally. Waiting for internet to auto-submit...', true);
    return;
  }

  uploadingPending=true;
  showWaitNetOverlay('Connected · Uploading your locked final submission...', false);
  $('btnForceUpload').disabled=true;

  try{
    var res=await fetch(SUB_URL,{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body:JSON.stringify({
        c:CSRF,
        aid:bundle.aid,
        qid:bundle.qid,
        r:bundle.r||'Submitted',
        la:bundle.la||null,
        a:bundle.a||[],
        v:bundle.v||[]
      })
    });
    var j=await res.json();
    if(j && j.success){
      try{ localStorage.removeItem(LS_KEY); localStorage.removeItem(PEND_KEY); }catch(e){}
      if(pendingRetryTimer){ clearTimeout(pendingRetryTimer); pendingRetryTimer=null; }
      if(navigator.keyboard && navigator.keyboard.unlock){ try{ navigator.keyboard.unlock(); }catch(e){} }
      if(document.fullscreenElement && document.exitFullscreen){ try{await document.exitFullscreen();}catch(e){} }
      window.location.href=j.redirect_url||DONE_URL;
      return;
    }
    throw new Error((j && j.message) || 'Server rejected submission');
  }catch(e){
    showWaitNetOverlay('Network unstable · Retrying auto-submit automatically every 5s...', true);
    $('btnForceUpload').disabled=false;
    if(!pendingRetryTimer){
      pendingRetryTimer=setTimeout(function(){ pendingRetryTimer=null; uploadPendingSubmit(); }, 5000);
    }
  }finally{
    uploadingPending=false;
  }
}

function doFinalSubmit(reason){
  if(locked) return;
  locked=true;
  submitted=true;
  $('btnConfirmSub').disabled=true;
  $('btnConfirmSub').textContent='Locking & Submitting...';

  // Freeze immutable offline submission bundle immediately (no edits possible after 00:00 or Submit!)
  var bundle={
    aid:B.aid,
    qid:B.qid,
    r:reason||'Submitted',
    la:serverNowIso(),
    a:buildCompactArray(false),
    v:vioQ.slice(0),
    done_url:DONE_URL
  };
  try{ localStorage.setItem(PEND_KEY, JSON.stringify(bundle)); }catch(e){}
  saveLocal();

  showWaitNetOverlay(
    navigator.onLine===false
      ? 'Offline · Answers locked safely. Will auto-submit the instant internet reconnects...'
      : 'Uploading your final submission...',
    navigator.onLine===false
  );
  uploadPendingSubmit();
}

var VIO_LABELS={
  TAB_SWITCH:'Tab switch or window minimization was detected.',
  WINDOW_BLUR:'You moved focus away from the exam window.',
  FULLSCREEN_EXIT:'Exiting fullscreen mode is strictly prohibited during the test.',
  COPY_ATTEMPT:'Copying, right-clicking, or restricted shortcuts are blocked.',
  PASTE_ATTEMPT:'Pasting content is strictly blocked.',
  DEVTOOLS_KEY:'Browser shortcuts (Esc / F11 / F12 / DevTools / Tab close) are blocked during the exam.'
};

function showViolationPopup(type, customText){
  if(submitted) return;
  document.body.classList.add('blur');
  $('fsOv').classList.remove('show');
  $('subOv').classList.remove('show');
  var reason=customText || VIO_LABELS[type] || 'Prohibited exam action detected.';
  var warnTail=vas
    ? (' Violation '+vc+' of '+mv+'. '+(vc>=mv?'Maximum limit reached — auto-submitting now...':((mv-vc)+' warning(s) remaining before automatic submission.')))
    : (' Total logged violations: '+vc+'.');
  $('vioTitle').textContent='⚠ Security Violation Detected ('+vc+(vas?('/'+mv):'')+')';
  $('vioMsg').textContent=reason+warnTail+' If you really want to end the test, click "Submit & Close Test".';
  $('vioOv').classList.add('show');
}

function lockKeys(){
  if(navigator.keyboard && typeof navigator.keyboard.lock==='function'){
    navigator.keyboard.lock(['Escape','F11','F12','Tab','AltLeft','AltRight','MetaLeft','MetaRight']).catch(function(){});
  }
}

function recordViolation(type, detail, customMsg){
  if(submitted || !startedFs) return;
  var now=Date.now();
  if(now - lastVioTs < 1200) return; // debounce rapid paired blur+visibility events
  lastVioTs=now;
  vc++;
  updateVioPill();
  vioQ.push({t:type, ts:serverNowIso(), d:detail||{}});
  saveLocal();
  showViolationPopup(type, customMsg);
  flushSync();
  if(vas && vc >= mv){
    doFinalSubmit('Auto_Submitted_Violation');
  }
}

function enterFullscreen(){
  var el=document.documentElement;
  var fn=el.requestFullscreen||el.webkitRequestFullscreen||el.msRequestFullscreen;
  var finish=function(){
    startedFs=true;
    lockKeys();
    document.body.classList.remove('blur');
    $('fsOv').classList.remove('show');
    $('vioOv').classList.remove('show');
    $('btnFsSubmit').style.display='block';
  };
  if(fn && !document.fullscreenElement){
    Promise.resolve(fn.call(el)).then(finish).catch(finish);
  } else {
    finish();
  }
}

function openSubmitPopup(){
  var aCnt=Object.keys(ans).filter(function(k){return !!ans[k];}).length;
  var rCnt=Object.keys(rev).filter(function(k){return !!rev[k];}).length;
  var uCnt=Q.length - aCnt;
  $('vioOv').classList.remove('show');
  $('fsOv').classList.remove('show');
  $('subMsg').textContent='Do you really want to submit and close the test? Answered: '+aCnt+' | Unanswered: '+uCnt+' | Marked for Review: '+rCnt+'. Once submitted, the test will close and you cannot re-enter.';
  $('subOv').classList.add('show');
}

$('btnEnterFs').onclick=enterFullscreen;
$('btnFsSubmit').onclick=openSubmitPopup;
$('btnVioOk').onclick=enterFullscreen;
$('btnVioSub').onclick=openSubmitPopup;
if(!reqFs){ startedFs=true; document.body.classList.remove('blur'); $('fsOv').classList.remove('show'); }

document.addEventListener('fullscreenchange',function(){
  if(reqFs && startedFs && !submitted && !document.fullscreenElement){
    recordViolation('FULLSCREEN_EXIT', {}, 'You exited fullscreen mode. Fullscreen is mandatory until you submit the test.');
  } else if(document.fullscreenElement){
    lockKeys();
  }
});

document.addEventListener('visibilitychange',function(){
  if(document.hidden){ recordViolation('TAB_SWITCH'); }
});
window.addEventListener('blur',function(){
  if(startedFs && !submitted){ recordViolation('WINDOW_BLUR'); }
});

// Block copy/paste/cut/right-click/selection/drag
['copy','cut','paste','contextmenu','dragstart','selectstart'].forEach(function(ev){
  document.addEventListener(ev,function(e){
    e.preventDefault();
    if(ev==='copy'||ev==='cut'||ev==='contextmenu') recordViolation('COPY_ATTEMPT', {ev:ev});
    if(ev==='paste') recordViolation('PASTE_ATTEMPT', {ev:ev});
  }, true);
});

// Capture-phase strict shortcut trap: blocks Esc, F11, F12, F1-F10, Ctrl+W/T/N/R/L/P/S/U/C/V/X/A, Alt, Meta
window.addEventListener('keydown',function(e){
  var k=e.key||'';
  var isFnKey=/^F([1-9]|1[0-2])$/i.test(k);
  var isEsc=(k==='Escape'||k==='Esc');
  var isMeta=(k==='Meta'||k==='OS'||e.metaKey);
  var isAltCombo=(e.altKey && (k==='Tab'||k==='F4'||k==='ArrowLeft'||k==='ArrowRight'||k==='Home'));
  var isCtrlBlocked=(e.ctrlKey && (/^[CVXAPSUWTNRLHJ]$/i.test(k) || (e.shiftKey && /^[IJCNT]$/i.test(k))));

  if(isEsc || isFnKey || isMeta || isAltCombo || isCtrlBlocked){
    e.preventDefault();
    e.stopImmediatePropagation();
    if(startedFs && !submitted){
      if(isEsc || k==='F11'){
        recordViolation('FULLSCREEN_EXIT', {key:k}, 'Shortcut "'+k+'" is blocked so fullscreen cannot be exited.');
      } else {
        recordViolation(isFnKey||e.shiftKey||isAltCombo||isMeta?'DEVTOOLS_KEY':'COPY_ATTEMPT', {key:k}, 'Shortcut "'+(e.ctrlKey?'Ctrl+':'')+(e.altKey?'Alt+':'')+(e.shiftKey?'Shift+':'')+k+'" is blocked during the exam.');
      }
    }
    return false;
  }

  if(!startedFs || $('subOv').classList.contains('show') || $('vioOv').classList.contains('show')) return;
  if(/^[1-6]$/.test(k)){
    var oi=parseInt(k,10)-1, cur=Q[idx];
    if(cur && cur.o[oi]){ ans[cur.d]=cur.o[oi][0]; delete rev[cur.d]; dirty[cur.d]=1; saveLocal(); render(); scheduleSync(false); }
  } else if(k==='n'||k==='N'){ if(idx<Q.length-1){ idx++; render(); } }
  else if(k==='p'||k==='P'){ if(idx>0){ idx--; render(); } }
  else if(k==='m'||k==='M'){ var d=Q[idx].d; if(rev[d]) delete rev[d]; else rev[d]=1; dirty[d]=1; saveLocal(); render(); scheduleSync(false); }
}, true);

$('btnPrev').onclick=function(){ if(idx>0){ idx--; render(); }};
$('btnNext').onclick=function(){
  var d=Q[idx].d;
  if(ans[d] && rev[d]){ delete rev[d]; dirty[d]=1; saveLocal(); scheduleSync(false); }
  if(idx<Q.length-1){ idx++; render(); } else { render(); scheduleSync(true); }
};
$('btnClr').onclick=function(){ var d=Q[idx].d; delete ans[d]; delete rev[d]; dirty[d]=1; saveLocal(); render(); scheduleSync(false); };
$('btnRev').onclick=function(){
  var d=Q[idx].d;
  if(rev[d]){ delete rev[d]; dirty[d]=1; saveLocal(); render(); scheduleSync(false); return; }
  rev[d]=1; dirty[d]=1; saveLocal(); if(idx<Q.length-1) idx++; render(); scheduleSync(false);
};

$('btnOpenSubmit').onclick=openSubmitPopup;
$('btnCancelSub').onclick=function(){ $('subOv').classList.remove('show'); if(reqFs && !document.fullscreenElement){ enterFullscreen(); } else { document.body.classList.remove('blur'); } };
$('btnConfirmSub').onclick=function(){ doFinalSubmit('Submitted'); };
$('btnForceUpload').onclick=function(){ uploadPendingSubmit(); };

window.addEventListener('beforeunload',function(e){
  if(!submitted){
    e.preventDefault(); e.returnValue='';
    if(navigator.sendBeacon && Object.keys(dirty).length){
      navigator.sendBeacon(SYNC_URL, new Blob([JSON.stringify({c:CSRF,aid:B.aid,qid:B.qid,a:buildCompactArray(true),v:vioQ})],{type:'application/json'}));
    }
  }
});
window.addEventListener('online',function(){
  setNet('p-ok','Reconnected · Syncing...');
  if(locked){ uploadPendingSubmit(); } else { flushSync(); }
});
window.addEventListener('offline',function(){
  setNet('p-warn','Offline · Saved Locally');
  if(locked){ showWaitNetOverlay('Offline · Saved locally. Waiting for internet to auto-submit...', true); }
});

updateVioPill();
render();
$('tmr').textContent=fmtTime(rem);

// If an offline-locked pending submission already exists in localStorage (e.g. after reload), lock immediately & auto-upload!
try{
  var existingPend=JSON.parse(localStorage.getItem(PEND_KEY)||'null');
  if(existingPend && Number(existingPend.aid)===Number(B.aid)){
    locked=true; submitted=true;
    showWaitNetOverlay('Pending offline submission detected · Syncing automatically...', navigator.onLine===false);
    uploadPendingSubmit();
  }
}catch(e){}

setInterval(tick, 1000);
setInterval(function(){ if(locked){ uploadPendingSubmit(); } else if(!submitted){ flushSync(); } }, 30000);
})();
</script>
</body>
</html>
