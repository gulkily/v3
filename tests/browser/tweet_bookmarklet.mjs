import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {chromium} from 'playwright-core';
const source=readFileSync('public/assets/tools_bookmarklets.js','utf8');
const extract=source.slice(source.indexOf('  function tweetComposeUrl('),source.indexOf('  function bookmarkletScript('));
const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,headless:true,args:['--no-sandbox']});
try {
 const page=await browser.newPage();
 await page.route('https://x.com/**',r=>r.fulfill({contentType:'text/html',body:'<!doctype html><title>Tweet</title>'}));
 await page.goto('https://x.com/author/status/123');
 const photo=(id,n,url=`https://pbs.twimg.com/media/${id}?format=jpg&name=small`)=>`<a href="/author/status/123/photo/${n}"><div data-testid="tweetPhoto"><img src="${url}"></div></a>`;
 const article=(id,content)=>`<article data-testid="tweet"><div data-testid="User-Name">Author<br>@author</div><div data-testid="tweetText">Tweet text<br>second line<img alt="😀" src="https://abs.twimg.com/emoji.png"></div><a href="/author/status/${id}"><time>Today</time></a>${content}</article>`;
 const run=async html=>{
  await page.setContent(html); await page.addScriptTag({content:extract});
  return new URL(await page.evaluate(()=>tweetComposeUrl('https://forum.test','tweet')));
 };
 const baseBody='Tweet text\nsecond line😀\n\n— Author (@author)\nhttps://x.com/author/status/123';
 let out=await run(article(123,''));assert.equal(out.searchParams.get('body'),baseBody);assert.equal(out.hash,'');
 out=await run(article(123,photo('First',1)));assert.equal(decodeURIComponent(out.hash),'#tweet-image=https://pbs.twimg.com/media/First?format=jpg&name=small');assert.match(out.searchParams.get('body'),/First\?format=jpg&name=small/);
 // Order by photo number; exclude another tweet's quote, video thumbnails, avatars, duplicate renditions and foreign URLs.
 out=await run(article(123,photo('Second',2)+photo('First',1)+photo('First',1,'https://pbs.twimg.com/media/First?format=png&name=large')+
  '<div role="link"><a href="/quote/status/999/photo/1"><img src="https://pbs.twimg.com/media/Quote?format=jpg"></a></div>'+
  '<div data-testid="videoPlayer"><img src="https://pbs.twimg.com/ext_tw_video_thumb/Video.jpg"></div>'+
  '<img src="https://pbs.twimg.com/profile_images/Avatar.jpg">'+photo('Unsafe',3,'https://pbs.twimg.com.evil.test/media/Fake.jpg')+
  photo('Unsafe',4,'https://user@pbs.twimg.com/media/Fake.jpg')+photo('Unsafe',5,'http://pbs.twimg.com/media/Fake.jpg')+
  article(999,photo('Nested',6))));
 assert.match(decodeURIComponent(out.hash),/media\/First\?/);const body=out.searchParams.get('body');assert(body.indexOf('/First?')<body.indexOf('/Second?'));assert.equal((body.match(/\/First\?/g)||[]).length,1);for(const excluded of ['Quote','Video','Avatar','Fake','Nested'])assert(!body.includes(excluded),excluded+": "+body);
 // Selection in another tweet takes precedence over the permalink; text and photo stay together.
 await page.setContent(article(123,photo('First',1))+article(456,'<a href="/author/status/456/photo/1"><img src="https://pbs.twimg.com/media/Selected.jpg:medium"></a>'));
 await page.addScriptTag({content:extract});
 await page.evaluate(()=>{const r=document.createRange();r.selectNodeContents(document.querySelectorAll('[data-testid="tweetText"]')[1]);const s=getSelection();s.removeAllRanges();s.addRange(r)});
 out=new URL(await page.evaluate(()=>tweetComposeUrl('https://forum.test','tweet')));assert.match(decodeURIComponent(out.hash),/Selected.jpg:medium/);assert.match(out.searchParams.get('body'),/status\/456$/);
 await page.setContent('<p>No tweet</p>');await page.addScriptTag({content:extract});await page.evaluate(()=>{window.alert=()=>{window.missingTweet=true}});assert.equal(await page.evaluate(()=>tweetComposeUrl('https://forum.test','tweet')),null);assert.equal(await page.evaluate(()=>window.missingTweet),true);
 console.log('PASS tweet photo ownership, ordering, links, exclusion, selection and no-tweet recovery');
} finally {await browser.close()}
