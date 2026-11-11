// Run against a newly installed, disposable kvskills_qa database only.
import assert from 'node:assert/strict';
import {spawn, spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import {createServer} from 'node:net';
import {readdirSync} from 'node:fs';
if (process.env.DB_NAME !== 'kvskills_qa' || process.env.DB_HOST !== '127.0.0.1' || process.env.DB_PORT !== '38493') throw Error('Disposable local database required');
const base = 'http://127.0.0.1:38494';
const email = randomUUID()+'@example.invalid';
const password = randomUUID(), replacement = randomUUID();
const env = {...process.env, APP_URL:base, QA_EMAIL:email, QA_PASSWORD:password};
function php(code) {
    const run = spawnSync('php',['-r',"require 'config/bootstrap.php';"+code],{env,encoding:'utf8'});
    assert.equal(run.status,0,run.stderr);
    return run.stdout.trim();
}
php(`$insert=db()->prepare("INSERT INTO users(role,status,full_name,email,password_hash) VALUES(?,'active','QA',?,?)");
foreach(['coach','technical'] as $role) $insert->execute([$role, $role==='coach'?getenv('QA_EMAIL'):'pt-'.getenv('QA_EMAIL'),password_hash(getenv('QA_PASSWORD'),PASSWORD_DEFAULT)]);`);
let emailText='';
const smtp = createServer(socket=>{
    socket.setEncoding('utf8');socket.write('220 localhost QA\r\n');
    let pending='',data=false;
    socket.on('data',chunk=>{
        pending+=chunk;
        let pos;
        while ((pos=pending.indexOf('\r\n'))>=0) {
            const line=pending.slice(0,pos);pending=pending.slice(pos+2);
            if(data && line!=='.'){emailText+=line+'\n';continue;}
            if(line==='.')data=false;
            if(line==='DATA'){data=true;socket.write('354 send\r\n');}
            else if(line==='QUIT')socket.end('221 bye\r\n');
            else socket.write('250 OK\r\n');
        }
    });
});
await new Promise((ok,fail)=>smtp.once('error',fail).listen(38495,'127.0.0.1',ok));
const server=spawn('php',['-d','SMTP=127.0.0.1','-d','smtp_port=38495','-d','sendmail_from=qa@example.invalid','-S','127.0.0.1:38494'],{env,stdio:['ignore','ignore','pipe']});
let log='';
server.stderr.on('data',s=>log+=s);
let checks=0;
async function request(jar,path,form,status=200) {
    const r=await fetch(base+'/'+path,{method:form?'POST':'GET',headers:jar.cookie?{Cookie:jar.cookie}:{},body:form?new URLSearchParams(form):undefined,redirect:'manual'});
    if(r.headers.get('set-cookie'))jar.cookie=r.headers.get('set-cookie').split(';')[0];
    const body=await r.text();
    assert.equal(r.status,status,path+' '+body.slice(0,80));
    assert.doesNotMatch(body,/Fatal error:|Warning:|SQLSTATE\[/i,path);
    checks++;return body;
}
async function csrf(jar){return JSON.parse(await request(jar,'api/v1/csrf.php')).token;}
async function login(jar,portal,email,password){
    await request(jar,portal+'/login.php',{_token:await csrf(jar),email,password},302);
    await request(jar,portal+'/dashboard.php');
}
try {
    for(let i=0;i<40;i++){try{await fetch(base+'/api/v1/health.php');break;}catch{await new Promise(r=>setTimeout(r,100));}}
    const guest={},coach={},pt={},admin={};
    for(const page of ['index','jawatan','maklumat/jadual','maklumat/lokasi','maklumat/peserta','maklumat/logo','maklumat/keputusan','jurulatih/register','pegawai_teknikal/register','jurulatih/forgot_password'])await request(guest,page+'.php');
    for(const page of ['catalog','health','briefings','documents','participants','results'])await request(guest,'api/v1/'+page+'.php');
    await request(guest,'api/v1/registrations.php',null,401);
    await request(guest,'api/v1/catalog.php',{},405);
    await request(guest,'download.php?id=-1',null,404);
    await login(coach,'jurulatih',email,password);
    await login(pt,'pegawai_teknikal','pt-'+email,password);
    await login(admin,'admin',env.INITIAL_ADMIN_EMAIL,env.INITIAL_ADMIN_PASSWORD);
    for(const [portal,jar] of [['admin',admin],['pegawai_teknikal',pt],['jurulatih',coach]]) {
        for(const file of readdirSync(portal).filter(f=>f.endsWith('.php')&&!['login.php','register.php','forgot_password.php'].includes(f))){
            await request(jar,portal+'/'+file,null,portal==='pegawai_teknikal'&&file==='pengguna.php'?302:200);
        }
    }
    await request(coach,'admin/pengguna.php',null,403);
    await request(pt,'admin/keputusan.php',null,403);
    await request(coach,'auth/password.php',{},419);
    const signup={},signupToken=await csrf(signup);
    const signupForm={_token:signupToken,coach_name:'QA Coach',coach_email:'new-'+email,coach_ic_number:'000101010101',zone_id:'2',institution:'QA College',coach_shirt_size:'M',password,confirm_password:password,participant_name:'QA Candidate',participant_shirt_size:'M',skill_id:php("echo db()->query('SELECT MIN(id) FROM skills WHERE assistant_count=0 AND model_count=0')->fetchColumn();")};
    await request(signup,'jurulatih/register.php',signupForm,302);
    php(`db()->exec("UPDATE competitions SET status='registration' WHERE id=(SELECT MIN(id) FROM (SELECT id FROM competitions) t)");`);
    await request(signup,'jurulatih/register.php',signupForm,302);
    await request(signup,'jurulatih/dashboard.php');
    const own=JSON.parse(await request(signup,'api/v1/registrations.php')).data;
    assert.equal(own.length,1);
    assert.equal(own[0].participant_name,'QA Candidate');
    await request(coach,'jurulatih/pendaftaran.php',{_token:await csrf(coach),action:'delete',id:own[0].id},302);
    assert.equal(JSON.parse(await request(signup,'api/v1/registrations.php')).data.length,1);
    await request(signup,'jurulatih/pendaftaran.php',{_token:await csrf(signup),action:'update_participant',id:own[0].id,participant_name:'QA Updated',shirt_size:'L'},302);
    assert.equal(JSON.parse(await request(signup,'api/v1/registrations.php')).data[0].participant_name,'QA Updated');
    await request(signup,'jurulatih/pendaftaran.php',{_token:await csrf(signup),action:'delete',id:own[0].id},302);
    assert.equal(JSON.parse(await request(signup,'api/v1/registrations.php')).data.length,0);
    await request(signup,'auth/logout.php',{_token:await csrf(signup)},302);
    await request(signup,'api/v1/registrations.php',null,401);
    if(env.QA_BROWSER_MODULE){
        const {chromium}=await import(env.QA_BROWSER_MODULE);
        const browser=await chromium.launch({channel:'chrome',headless:true});
        try {
            const page=await browser.newPage();
            const errors=[];
            page.on('pageerror',e=>errors.push(e.message));
            for(const width of [360,768,1280]){
                await page.setViewportSize({width,height:900});
                for(const route of ['index.php','jurulatih/login.php','jurulatih/forgot_password.php']){
                    await page.goto(base+'/'+route,{waitUntil:'domcontentloaded'});
                    const metrics=await page.evaluate(()=>({width:document.documentElement.clientWidth,content:document.documentElement.scrollWidth,language:document.documentElement.lang}));
                    assert.equal(metrics.language,'ms');
                    assert.ok(metrics.content<=metrics.width+1,route+' overflows at '+width);
                    await page.keyboard.press('Tab');
                    assert.ok(await page.evaluate(()=>document.activeElement!==document.body));
                }
            }
            assert.deepEqual(errors,[]);
            console.log('Browser layout and keyboard checks passed at 360, 768 and 1280 px.');
        } finally {await browser.close();}
    }
    const rid=php(`$s=db()->prepare("INSERT INTO registrations(competition_id,zone_id,skill_id,coach_user_id,participant_name,institution,status) SELECT (SELECT MIN(id) FROM competitions),(SELECT MAX(id) FROM zones),(SELECT MIN(id) FROM skills),id,'QA','QA','submitted' FROM users WHERE email=?");$s->execute([getenv('QA_EMAIL')]);echo db()->lastInsertId();`);
    // First skill may require helpers. Populate the exact seed requirements.
    php(`$r=db()->query("SELECT s.* FROM skills s JOIN registrations r ON r.skill_id=s.id WHERE r.id=${rid}")->fetch();
    foreach(['assistant','model'] as $type) for($i=0;$i<$r[$type.'_count'];$i++)db()->prepare("INSERT INTO registration_helpers(registration_id,helper_type,full_name) VALUES(?,?,?)")->execute([${rid},$type,'QA Helper']);`);
    const ptToken=await csrf(pt),adminToken=await csrf(admin);
    await request(pt,'pegawai_teknikal/pendaftaran.php',{_token:ptToken,id:rid,action:'approve'},302);
    await request(pt,'pegawai_teknikal/pendaftaran.php',{_token:ptToken,id:rid,action:'reject',reason:'stale'},302);
    assert.equal(php(`echo db()->query("SELECT status FROM registrations WHERE id=${rid}")->fetchColumn();`),'approved');
    const score={_token:ptToken,registration_id:rid,skill_category:'heavy',score:'abc',rank_position:php("echo 1+(int)db()->query('SELECT MAX(rank_position) FROM results')->fetchColumn();")};
    await request(pt,'pegawai_teknikal/keputusan.php',score,302);
    assert.equal(php(`echo db()->query("SELECT COUNT(*) FROM results WHERE registration_id=${rid}")->fetchColumn();`),'0');
    await request(pt,'pegawai_teknikal/keputusan.php',{...score,score:'90'},302);
    assert.equal(php(`echo db()->query("SELECT award FROM results WHERE registration_id=${rid}")->fetchColumn();`),'Emas');
    await request(admin,'admin/keputusan.php',{...score,_token:adminToken,action:'save_score',score:'0'},302);
    await request(admin,'admin/keputusan.php',{_token:adminToken,action:'publish_all'},302);
    assert.equal(php(`echo db()->query("SELECT is_published FROM results WHERE registration_id=${rid} AND score=0")->fetchColumn();`),'1');
    const second=php(`db()->exec("INSERT INTO registrations(competition_id,zone_id,skill_id,coach_user_id,participant_name,institution,status)
        SELECT competition_id,3,skill_id,coach_user_id,'QA second','QA','approved' FROM registrations WHERE id=${rid}");echo db()->lastInsertId();`);
    const nextRank=String(Number(score.rank_position)+1);
    const worker=id=>new Promise((resolve,reject)=>{
        const script=`require 'config/bootstrap.php';require 'app/ResultEntry.php';
            if(!Auth::attempt(getenv('INITIAL_ADMIN_EMAIL'),getenv('INITIAL_ADMIN_PASSWORD'),'admin'))exit(2);
            try{ResultEntry::save(['registration_id'=>getenv('QA_REG'),'score'=>'90','rank_position'=>getenv('QA_RANK'),'skill_category'=>'heavy'],true);echo 'ok';}
            catch(DomainException $e){echo $e->getMessage();}`;
        const child=spawn('php',['-r',script],{env:{...env,QA_REG:String(id),QA_RANK:nextRank}});
        let output='';child.stdout.on('data',chunk=>output+=chunk);child.stderr.on('data',chunk=>output+=chunk);
        child.on('close',code=>code===0?resolve(output):reject(Error(output)));
    });
    const race=await Promise.all([worker(rid),worker(second)]);
    assert.equal(race.filter(v=>v==='ok').length,1);
    assert.equal(race.filter(v=>v.includes('Kedudukan telah digunakan')).length,1);
    const docId=php(`$s=db()->prepare("INSERT INTO documents(title,category,file_path,original_filename,mime_type,file_size,checksum_sha256,visibility,is_active) VALUES('QA private','other',?,'qa.pdf','application/pdf',0,REPEAT('0',64),'private',1)");$s->execute(['qa/'.getenv('QA_EMAIL')]);echo db()->lastInsertId();`);
    await request(guest,'download.php?id='+docId,null,403);
    await request(coach,'download.php?id='+docId,null,403);
    await request(pt,'download.php?id='+docId,null,404);
    const docs=JSON.parse(await request(guest,'api/v1/documents.php')).data;
    assert.ok(docs.length>0 && !docs.some(d=>String(d.id)===docId));
    const file=await fetch(docs[0].download_url);
    assert.equal(file.status,200);
    assert.equal((await file.arrayBuffer()).byteLength,Number(docs[0].file_size));
    const invalidFile=new FormData();
    for (const [key,value] of Object.entries({_token:ptToken,title:'QA invalid upload',category:'other',visibility:'private'}))invalidFile.set(key,value);
    invalidFile.set('document',new Blob(['<?php echo 1; ?>'],{type:'application/pdf'}),'test.pdf');
    const rejected=await fetch(base+'/pegawai_teknikal/dokumen.php',{method:'POST',headers:{Cookie:pt.cookie},body:invalidFile,redirect:'manual'});
    assert.equal(rejected.status,302);
    assert.equal(php(`echo db()->query("SELECT COUNT(*) FROM documents WHERE title='QA invalid upload'")->fetchColumn();`),'0');
    php(`db()->prepare("DELETE FROM documents WHERE id=?")->execute([${docId}]);`);
    php(`db()->prepare("UPDATE users SET role='coach' WHERE email=?")->execute(['pt-'.getenv('QA_EMAIL')]);`);
    await request(pt,'pegawai_teknikal/keputusan.php',null,403);
    php(`db()->prepare("UPDATE users SET status='disabled' WHERE email=?")->execute(['pt-'.getenv('QA_EMAIL')]);`);
    await request(pt,'api/v1/registrations.php',null,401);
    const recovery='jurulatih/forgot_password.php',token=await csrf(guest);
    await request(guest,recovery,{_token:token,email:'missing@example.invalid'},302);
    const absent=await request(guest,recovery);
    await request(guest,recovery,{_token:token,email},302);
    assert.equal(await request(guest,recovery),absent);
    const link=emailText.match(/forgot_password\.php\?token=([^\s]+)/);
    assert.ok(link,'Expected recovery link at the local SMTP receiver');
    const reset={_token:token,token:link[1],new_password:replacement,confirm_password:replacement};
    await request(guest,recovery,reset,302);
    assert.match(await request(guest,recovery,reset),/tidak sah atau telah tamat/);
    await request(coach,'api/v1/registrations.php',null,401);
    await login({},'jurulatih',email,replacement);
    for(let i=0;i<8;i++){const jar={};await request(jar,'jurulatih/login.php',{_token:await csrf(jar),email,password:'wrong'});}
    const fresh={};await request(fresh,'jurulatih/login.php',{_token:await csrf(fresh),email,password:replacement});
    await request(fresh,'api/v1/registrations.php',null,401);
    assert.doesNotMatch(log,/PHP (Warning|Fatal error|Notice|Deprecated)/);
    console.log(checks+' HTTP checks passed, including role pages, scoring, SMTP recovery, replay, session revocation and throttling.');
} finally {
    server.kill();
    smtp.close();
    php(`$q=db()->prepare("DELETE r FROM registrations r JOIN users u ON u.id=r.coach_user_id WHERE u.email=?");$q->execute([getenv('QA_EMAIL')]);
        $q=db()->prepare("DELETE FROM users WHERE email IN (?,?,?)");$q->execute([getenv('QA_EMAIL'),'pt-'.getenv('QA_EMAIL'),'new-'.getenv('QA_EMAIL')]);`);
}
