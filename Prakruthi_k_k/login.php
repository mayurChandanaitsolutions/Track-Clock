<!DOCTYPE html>
<html>
<head>
<title>Employee Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,sans-serif; background:#eef2f7; padding:15px; }
h2 { color:#1a2636; text-align:center; padding:12px 0; font-size:20px; }
.container { max-width:400px; margin:0 auto; }
.card { background:white; border-radius:12px; padding:15px; margin-bottom:14px; box-shadow:0 2px 10px rgba(0,0,0,0.09); }
video, #preview-img { width:100%; border-radius:8px; border:2px solid #ddd; display:block; }
canvas { display:none; }
#preview-img { display:none; }
#status { margin-top:10px; padding:12px 14px; border-radius:8px; font-size:13px; border-left:4px solid #f39c12; background:#fff8e1; color:#7d6608; line-height:1.8; }
#status.ready { background:#e9f7ef; color:#1e8449; border-left-color:#27ae60; }
#status.error { background:#fdf2f2; color:#c0392b; border-left-color:#e74c3c; }
.spinner { display:inline-block; width:13px; height:13px; border:2px solid #f39c12; border-top-color:transparent; border-radius:50%; animation:spin 0.8s linear infinite; vertical-align:middle; margin-right:6px; }
@keyframes spin { to { transform:rotate(360deg); } }
.btn-row { display:flex; gap:10px; margin-top:12px; }
.btn { flex:1; padding:14px; border:none; border-radius:8px; font-size:15px; font-weight:bold; cursor:pointer; }
.btn-green  { background:#27ae60; color:white; }
.btn-green:hover:not(:disabled) { background:#219150; }
.btn-orange { background:#e67e22; color:white; display:none; }
.btn-blue   { background:#2980b9; color:white; display:none; }
.btn:disabled { background:#bdc3c7; cursor:not-allowed; }
</style>
</head>
<body>
<div class="container">
  <h2>&#128994; Employee Login (IN)</h2>
  <div class="card">
    <video id="video" autoplay playsinline></video>
    <img id="preview-img" alt="Captured photo">
    <canvas id="canvas" width="400" height="320"></canvas>
    <div id="status"><span class="spinner"></span> Getting GPS location...</div>
    <div class="btn-row">
      <button class="btn btn-green"  id="captureBtn"   onclick="doCapture()"   disabled>&#128248; Capture</button>
      <button class="btn btn-orange" id="recaptureBtn" onclick="doRecapture()">&#128260; Recapture</button>
      <button class="btn btn-blue"   id="saveBtn"      onclick="doSave()">&#128190; Save Login</button>
    </div>
  </div>
</div>
<script>
var video=document.getElementById('video'),canvas=document.getElementById('canvas'),
    previewImg=document.getElementById('preview-img'),statusEl=document.getElementById('status');
var gpsLat=null,gpsLon=null,gpsAccuracy=null,finalAddress=null,capturedImage=null;
var watchId=null,bestAcc=99999,readingCount=0;

// Camera
navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false})
  .then(function(s){video.srcObject=s;})
  .catch(function(){
    navigator.mediaDevices.getUserMedia({video:true})
      .then(function(s){video.srcObject=s;})
      .catch(function(){alert('Please allow camera access!');});
  });

// GPS — watch until accuracy <= 50m or 3 good readings
window.addEventListener('load',function(){
  setStatus('loading','&#128225; Getting GPS... please wait...');
  watchId=navigator.geolocation.watchPosition(
    function(pos){
      var acc=Math.round(pos.coords.accuracy);
      var lat=pos.coords.latitude;
      var lon=pos.coords.longitude;
      readingCount++;
      setStatus('loading','&#128225; GPS accuracy: <b>+-'+acc+'m</b><br>&#9200; Locking to your exact location...');
      // Accept if accuracy improved AND at least 50m or got 3 readings
      if(acc < bestAcc){
        bestAcc=acc;
        gpsLat=lat; gpsLon=lon; gpsAccuracy=acc;
      }
      // Stop when accuracy good enough or enough readings taken
      if(acc<=50 || readingCount>=3){
        navigator.geolocation.clearWatch(watchId);
        setStatus('loading','&#128204; GPS locked (+-'+bestAcc+'m)<br>&#128269; Fetching your address...');
        fetchAddress(gpsLat,gpsLon);
      }
    },
    function(){setStatus('error','&#10060; Location denied. Please allow and refresh.');},
    {enableHighAccuracy:true,timeout:30000,maximumAge:0}
  );
  // Hard stop after 25 seconds - use best reading we have
  setTimeout(function(){
    if(finalAddress) return;
    if(watchId) navigator.geolocation.clearWatch(watchId);
    if(gpsLat){
      setStatus('loading','&#128204; Using best GPS (+-'+bestAcc+'m)<br>&#128269; Fetching address...');
      fetchAddress(gpsLat,gpsLon);
    } else {
      setStatus('error','&#10060; GPS timeout. Please allow location and refresh.');
    }
  },25000);
});

// Fetch address using Nominatim with zoom=14 for taluk level
// then use state_district field which is more reliable than county for India
function fetchAddress(lat,lon){
  // Call Nominatim with zoom=14 (best for village+district level in India)
  fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+lat+'&lon='+lon+'&zoom=14&addressdetails=1&accept-language=en&namedetails=1',
    {headers:{'Accept-Language':'en','User-Agent':'AttendanceApp/1.0'}})
  .then(function(r){return r.json();})
  .then(function(d){
    if(!d||!d.address){fallbackBDC(lat,lon);return;}
    var a=d.address;

    // Village: most specific place name
    var village=a.village||a.hamlet||a.suburb||a.neighbourhood||a.town||a.city_district||'';

    // District: In India Nominatim uses state_district for actual district
    // county sometimes gives taluk or division — use state_district first
    var district='';
    if(a.state_district) district=a.state_district.replace(/ [Dd]istrict$/,'').trim();
    else if(a.county)    district=a.county.replace(/ [Dd]istrict$/,'').trim();

    var state=a.state||'';
    var country=a.country||'';

    if(village && district && state){
      buildAddress(village,district,state,country);
    } else {
      fallbackBDC(lat,lon);
    }
  })
  .catch(function(){fallbackBDC(lat,lon);});
}

// Fallback: BigDataCloud with adminLevel 6 = district
function fallbackBDC(lat,lon){
  fetch('https://api.bigdatacloud.net/data/reverse-geocode-client?latitude='+lat+'&longitude='+lon+'&localityLanguage=en')
  .then(function(r){return r.json();})
  .then(function(d){
    if(!d){showCoords();return;}
    var village='',district='',state='',country='';
    if(d.localityInfo&&d.localityInfo.administrative){
      d.localityInfo.administrative.forEach(function(a){
        var n=(a.name||'').trim();
        if(!n)return;
        if(a.adminLevel===6) district=n;
        if(a.adminLevel>=8&&!village) village=n;
        if(a.adminLevel===7&&!village) village=n;
        if(a.adminLevel===4) state=n;
        if(a.adminLevel===2) country=n;
      });
    }
    if(!village) village=d.locality||'';
    if(!state)   state=d.principalSubdivision||'';
    if(!country) country=d.countryName||'';
    buildAddress(village,district,state,country);
  })
  .catch(function(){showCoords();});
}

function buildAddress(village,district,state,country){
  district=district.replace(/ [Dd]istrict$/,'').trim();
  var parts=[];
  if(village)  parts.push(village);
  if(district) parts.push(district+' District');
  if(state)    parts.push(state);
  if(country)  parts.push(country);
  finalAddress=parts.length>=2?parts.join(', '):null;
  if(!finalAddress){showCoords();return;}
  setStatus('ready','&#9989; Location ready!<br>&#128205; <b>'+finalAddress+'</b><br>&#127991; Accuracy: +-'+gpsAccuracy+'m');
  document.getElementById('captureBtn').disabled=false;
}

function showCoords(){
  finalAddress=gpsLat.toFixed(5)+'N, '+gpsLon.toFixed(5)+'E';
  setStatus('ready','&#9989; GPS ready (no address found)<br>&#128205; '+finalAddress);
  document.getElementById('captureBtn').disabled=false;
}

function drawWrapped(ctx,text,x,y,maxW,lh){
  var words=text.split(' '),line='',lines=[];
  for(var i=0;i<words.length;i++){
    var t=line+words[i]+' ';
    if(ctx.measureText(t).width>maxW&&line){lines.push(line.trim());line=words[i]+' ';}
    else line=t;
  }
  lines.push(line.trim());
  for(var j=0;j<lines.length;j++)ctx.fillText(lines[j],x,y+j*lh);
}

function doCapture(){
  if(!finalAddress){alert('Location not ready yet. Please wait.');return;}
  var ctx=canvas.getContext('2d');
  ctx.drawImage(video,0,0,canvas.width,canvas.height);
  var now=new Date();
  var dd=String(now.getDate()).padStart(2,'0');
  var mm=String(now.getMonth()+1).padStart(2,'0');
  var yyyy=now.getFullYear();
  var hh=String(now.getHours()).padStart(2,'0');
  var mi=String(now.getMinutes()).padStart(2,'0');
  var ss=String(now.getSeconds()).padStart(2,'0');
  var dateStr=dd+'/'+mm+'/'+yyyy;
  var timeStr=hh+':'+mi+':'+ss;
  var oh=115;
  ctx.fillStyle='rgba(0,0,0,0.80)';
  ctx.fillRect(0,canvas.height-oh,canvas.width,oh);
  ctx.fillStyle='#ffffff';ctx.font='bold 14px Arial';
  ctx.fillText('LOGIN',10,canvas.height-oh+18);
  ctx.font='12px Arial';ctx.fillStyle='#ffffff';
  ctx.fillText('Date : '+dateStr,10,canvas.height-oh+35);
  ctx.fillText('Time : '+timeStr,10,canvas.height-oh+51);
  ctx.font='bold 10px Arial';ctx.fillStyle='#aaffaa';
  ctx.fillText('Location :',10,canvas.height-oh+67);
  ctx.font='10px Arial';ctx.fillStyle='#90ee90';
  drawWrapped(ctx,finalAddress,10,canvas.height-oh+81,canvas.width-15,13);
  capturedImage=canvas.toDataURL('image/png');
  previewImg.src=capturedImage;
  video.style.display='none';previewImg.style.display='block';
  document.getElementById('captureBtn').style.display='none';
  document.getElementById('recaptureBtn').style.display='block';
  document.getElementById('saveBtn').style.display='block';
  setStatus('ready','&#9989; Photo captured!<br>&#128205; '+finalAddress+'<br>&#128197; '+dateStr+' &#128336; '+timeStr);
}

function doRecapture(){
  capturedImage=null;
  previewImg.style.display='none';video.style.display='block';
  document.getElementById('captureBtn').style.display='block';
  document.getElementById('recaptureBtn').style.display='none';
  document.getElementById('saveBtn').style.display='none';
  setStatus('ready','&#9989; Location ready:<br>&#128205; '+finalAddress);
}

function doSave(){
  if(!capturedImage){alert('Please capture photo first.');return;}
  var btn=document.getElementById('saveBtn');
  btn.disabled=true;btn.textContent='Saving...';
  var body='image='+encodeURIComponent(capturedImage)
    +'&latitude='+gpsLat+'&longitude='+gpsLon
    +'&accuracy='+gpsAccuracy
    +'&address='+encodeURIComponent(finalAddress);
  fetch('save_login.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
  .then(function(){
    alert('Login Saved!\n\n'+finalAddress);
    window.location='logout.php';
  }).catch(function(){
    alert('Save failed. Please try again.');
    btn.disabled=false;btn.textContent='Save Login';
  });
}

function setStatus(type,msg){
  statusEl.className=type==='ready'?'ready':type==='error'?'error':'';
  statusEl.innerHTML=(type==='loading'?'<span class="spinner"></span>':'')+msg;
}
</script>
</body>
</html>
