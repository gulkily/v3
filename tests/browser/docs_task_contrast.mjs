// Run against a Docs host containing tests/fixtures/markdown_task_reader.md at DOCS_TASK_FIXTURE_PATH.
// Theme paint is applied explicitly because contexts intentionally disable preference JavaScript.
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';

const base = process.env.DOCS_BASE_URL || 'http://127.0.0.1:18087';
const route = process.env.DOCS_TASK_FIXTURE_PATH || '/docs/examples/markdown_task_fixture.md';
const output = process.env.DOCS_EVIDENCE_ROOT || '/tmp/docs-task-contrast';
await mkdir(output, {recursive:true});
const names = JSON.parse(execFileSync('php', ['-r', "require 'autoload.php'; echo json_encode(ForumRewrite\\View\\ThemeRegistry::explicitNames());"], {encoding:'utf8'}));
const cases = [
  {name:'auto-light', theme:null, scheme:'light'}, {name:'auto-dark', theme:null, scheme:'dark'},
  ...names.map(theme => ({name:theme, theme, scheme:'light'})),
  ...['amber','purple','gray'].map(palette => ({name:'lcd-'+palette, theme:'lcd', palette, scheme:'light'})),
  {name:'forced-light', theme:null, scheme:'light', forced:true}, {name:'forced-dark', theme:null, scheme:'dark', forced:true},
];
const browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless:true, args:['--no-sandbox']});
const evidence = [];
try {
  for (const width of [1280,390]) for (const entry of cases) {
    const context = await browser.newContext({viewport:{width,height:900}, javaScriptEnabled:false, colorScheme:entry.scheme, forcedColors:entry.forced?'active':'none'});
    const page = await context.newPage();
    await page.goto(base+route);
    await page.evaluate(({theme,palette}) => {
      document.getElementById('theme-stylesheet')?.remove();
      document.documentElement.removeAttribute('data-theme');
      document.documentElement.removeAttribute('data-lcd-palette');
      if (theme) document.documentElement.dataset.theme=theme;
      if (palette) document.documentElement.dataset.lcdPalette=palette;
    }, entry);
    if (entry.theme) await page.evaluate(css => { const style=document.createElement('style'); style.textContent=css; document.head.append(style); }, await readFile('public/assets/theme-'+entry.theme+'.css','utf8'));
    const pair = page.getByRole('checkbox', {name:'Same task',exact:true});
    assert.equal(await pair.count(),2);
    const values = await pair.evaluateAll(nodes => nodes.map(e => {
      const rgba = value => {
        const n = value.match(/[\d.]+/g)?.map(Number);
        if (!n || n.length<3) throw new Error('Unexpected color '+value);
        return [...n.slice(0,3), n.length>3?n[3]:1];
      };
      const over=(a,b)=>[...a.slice(0,3).map((v,i)=>v*a[3]+b[i]*(1-a[3])),1];
      const luminance=c=>c.slice(0,3).map(v=>v/255).map(v=>v<=0.04045?v/12.92:((v+0.055)/1.055)**2.4).reduce((v,x,i)=>v+x*[0.2126,0.7152,0.0722][i],0);
      const ratio=(a,b)=>{const x=luminance(a),y=luminance(b);return (Math.max(x,y)+0.05)/(Math.min(x,y)+0.05);};
      let backdrop=[255,255,255,1]; const ancestors=[];
      for(let p=e.parentElement;p;p=p.parentElement) ancestors.unshift(p);
      for(const p of ancestors) backdrop=over(rgba(getComputedStyle(p).backgroundColor),backdrop);
      const style=getComputedStyle(e), before=getComputedStyle(e,'::before');
      const fill=over(rgba(style.backgroundColor),backdrop), border=rgba(style.borderTopColor);
      const mark=over(rgba(before.backgroundColor),fill);
      return {checked:e.checked, disabled:e.disabled, fill, border, backdrop, mark,
        borderContrast:ratio(border,backdrop), markContrast:ratio(mark,fill),
        clip:before.clipPath, markContent:before.content, appearance:style.appearance,
        opacity:style.opacity, width:e.getBoundingClientRect().width,
        source:getComputedStyle(e.closest('li')).listStyleType};
    }));
    assert.deepEqual(values.map(e=>e.checked),[false,true]);
    assert.ok(values.every(e=>e.disabled && e.opacity==='1' && e.width>=16), JSON.stringify({entry,width,values}));
    assert.ok(values[0].borderContrast>=3, JSON.stringify({entry,width,values}));
    assert.ok(values[1].markContrast>=3, JSON.stringify({entry,width,values}));
    assert.ok(values[1].clip.startsWith('polygon('), 'Checked state has a drawn check shape');
    assert.equal(values[0].markContent,'none');
    assert.notDeepEqual(values[0].fill,values[1].fill, 'State differs in fill as well as check shape');
    await pair.first().evaluate(e=>e.scrollIntoView({block:'center',behavior:'instant'}));
    const clip=await pair.evaluateAll(nodes=>{
      const label=nodes.map(e=>e.labels[0].getBoundingClientRect());
      const box=nodes.map(e=>e.getBoundingClientRect());
      const x=Math.max(0,Math.min(...box.map(r=>r.x))-6),y=Math.max(0,Math.min(...box.map(r=>r.y))-6);
      return {x,y,width:Math.min(innerWidth-x,Math.max(...label.map(r=>r.right))-x+6),height:Math.max(...box.map(r=>r.bottom))-y+6};
    });
    const screenshot=entry.name+'-'+width+'.png';
    await page.screenshot({path:output+'/'+screenshot,clip});
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
    evidence.push({theme:entry.name,width,scheme:entry.scheme,forced:!!entry.forced,values,screenshot});
    console.log('PASS task state contrast',entry.name,width);
    await context.close();
  }
  await writeFile(output+'/evidence.json',JSON.stringify({base,minimumContrast:3,cases:evidence.length,evidence},null,2)+'\n');
} finally { await browser.close(); }
