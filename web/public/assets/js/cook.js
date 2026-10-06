let pages=[], index=0, wakeLock=null;
const root=document.querySelector('.cook-shell'); const id=root.dataset.recipeId;
const page=document.getElementById('cook-page'), counter=document.getElementById('cook-counter');
function splitSteps(text){return String(text||'').split(/\r?\n/).map(s=>s.replace(/^\s*\d+\.(?!$)/,'').trim()).filter(Boolean)}
function render(){page.innerHTML=''; const p=pages[index]||{title:'',text:''}; const h=document.createElement('h1');h.textContent=p.title; const t=document.createElement('div');t.className='cook-text';t.textContent=p.text;page.append(h,t);counter.textContent=`${index+1} / ${pages.length}`;document.getElementById('cook-prev').disabled=index<=0;document.getElementById('cook-next').disabled=index>=pages.length-1;}
async function load(){const r=await fetch(`/api/recipes/${encodeURIComponent(id)}`); if(!r.ok) throw new Error(r.status); const d=await r.json();document.getElementById('cook-title').textContent=d.title||'Kochmodus';pages=[{title:'Zutaten',text:d.ingredients||'Keine Zutaten angegeben.'},...splitSteps(d.directions).map((x,i)=>({title:`Schritt ${i+1}`,text:x}))];render();}
async function requestWake(){try{if('wakeLock' in navigator){wakeLock=await navigator.wakeLock.request('screen');document.getElementById('cook-wake').textContent='Display bleibt an';}}catch(e){console.warn(e)}}
document.getElementById('cook-prev').onclick=()=>{if(index>0){index--;render()}};document.getElementById('cook-next').onclick=()=>{if(index<pages.length-1){index++;render()}};document.getElementById('cook-close').onclick=()=>history.length>1?history.back():location.assign('/recipes');document.getElementById('cook-wake').onclick=requestWake;
document.addEventListener('keydown',e=>{if(e.key==='ArrowRight')document.getElementById('cook-next').click();if(e.key==='ArrowLeft')document.getElementById('cook-prev').click()});
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible'&&wakeLock)requestWake()});
let sx=0;page.addEventListener('touchstart',e=>sx=e.changedTouches[0].clientX,{passive:true});page.addEventListener('touchend',e=>{const dx=e.changedTouches[0].clientX-sx;if(dx<-60)document.getElementById('cook-next').click();if(dx>60)document.getElementById('cook-prev').click()},{passive:true});
load().then(requestWake).catch(()=>{page.textContent='Rezept konnte nicht geladen werden.'});
