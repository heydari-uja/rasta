import {test, expect} from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

async function contrastOf(page, selector) {
  return page.locator(selector).first().evaluate(element => {
    const {color, backgroundColor}=getComputedStyle(element);
    const channels=value=>value.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>v/255).map(v=>v<=0.04045?v/12.92:((v+0.055)/1.055)**2.4);
    const luminance=value=>channels(value).reduce((sum,v,i)=>sum+v*[0.2126,0.7152,0.0722][i],0);
    const a=luminance(color), b=luminance(backgroundColor);
    return (Math.max(a,b)+0.05)/(Math.min(a,b)+0.05);
  });
}

async function computedTokenColor(page, tokenName) {
  return page.evaluate(name => {
    const probe = document.createElement('span');
    probe.style.color = `var(--${name})`;
    document.body.append(probe);
    const color = getComputedStyle(probe).color;
    probe.remove();
    return color;
  }, tokenName);
}

async function captureThemePair(page, screenshotDir, name) {
  await expect(page.locator('html')).toHaveAttribute('data-theme','dark');
  const misplacedActions=await page.evaluate(()=>{
    const groups=document.querySelectorAll('.button-row,.task-editor-actions,.column-create-actions,.settings-account-actions,.settings-dialog-actions,.comment-composer-actions');
    return [...groups].flatMap(group=>{
      const buttons=[...group.children].filter(el=>el.matches('button')&&el.getClientRects().length);
      const primary=buttons.find(el=>el.matches('.primary,.danger'));
      if(!primary)return [];
      return buttons.some(el=>el!==primary&&el.getBoundingClientRect().left<primary.getBoundingClientRect().left-1)?[group.className]:[];
    });
  });
  expect(misplacedActions).toEqual([]);
  await page.screenshot({path:path.join(screenshotDir,`${name}-dark.png`),fullPage:true});
}

async function expectSidebarHoverTokens(page) {
  const expected = await page.evaluate(() => {
    const probe = document.createElement('span');
    probe.style.backgroundColor = 'var(--surface-muted)';
    document.body.append(probe);
    const color = getComputedStyle(probe).backgroundColor;
    probe.remove();
    return color;
  });
  for (const selector of ['.sidebar-profile-button']) {
    const control = page.locator(selector).first();
    await control.hover();
    await expect(control).toHaveCSS('background-color', expected);
  }
  const projectRow = page.locator('.sidebar-project-item').first();
  await projectRow.hover();
  await expect(projectRow).toHaveCSS('background-color', expected);
}

test('workspace flows and resolved color themes', async ({page}) => {
  const screenshotDir=path.resolve('docs/color-audit/screenshots');
  fs.mkdirSync(screenshotDir,{recursive:true});
  const errors=[];
  page.on('pageerror', error => errors.push(error.message));
  await page.setViewportSize({width:1440,height:1000});
  await page.goto('/login');
  await page.evaluate(()=>localStorage.setItem('rasta-theme','dark'));
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-theme','dark');
  await captureThemePair(page,screenshotDir,'login-member');
  await page.locator('.login-mode').getByRole('button',{name:'مدیر',exact:true}).click();
  await captureThemePair(page,screenshotDir,'login-admin');
  await page.locator('.login-mode').getByRole('button',{name:'عضو',exact:true}).click();
  await page.locator('[name=pin]').fill(process.env.RASTA_TEST_MEMBER_PIN);
  await page.getByRole('button',{name:'ورود به فضای کار'}).click();
  await expect(page.locator('html')).toHaveAttribute('data-theme',/^(light|dark)$/);
  await expect(page.getByRole('heading',{name:'شما کی هستید؟'})).toBeVisible();
  await captureThemePair(page,screenshotDir,'profile-selection');
  await page.locator('[name=name]').fill('عضو آزمون');
  await page.locator('[name=job_title]').fill('طراح');
  await page.getByRole('button',{name:'افزودن خودم',exact:true}).click();
  await expect(page.locator('.tasks-page')).toBeVisible();
  await expect(page.getByRole('heading',{name:'هنوز مرحله‌ای ندارید'})).toBeVisible();
  await captureThemePair(page,screenshotDir,'my-tasks-no-columns');

  await page.getByRole('button',{name:'ساخت اولین پروژه'}).click();
  const dialog=page.getByRole('dialog');
  await expect(dialog).toBeVisible();
  const projectPalette=dialog.locator('.project-color-palette').first();
  await expect(projectPalette.locator('.project-color-choice')).toHaveCount(8);
  const firstMarkerColor=await page.evaluate(()=>{const probe=document.createElement('span');probe.style.color='var(--project-marker-1)';document.body.append(probe);const color=getComputedStyle(probe).color;probe.remove();return color;});
  await expect(projectPalette.locator('.project-color-current-swatch')).toHaveCSS('background-color',firstMarkerColor);
  await dialog.locator('[x-model="projectForm.name"]').fill('پروژه آزمون رابط');
  await dialog.locator('.project-color-trigger').click();
  await expect(dialog.locator('.project-color-options')).toBeVisible();
  await captureThemePair(page,screenshotDir,'project-wizard-color-picker');
  await dialog.locator('.project-color-choice').first().click();
  await captureThemePair(page,screenshotDir,'project-wizard-details');
  await dialog.getByRole('button',{name:'ادامه',exact:true}).click();
  await dialog.locator('[x-model="projectForm.goal.title"]').fill('رسیدن به نتیجه قابل سنجش');
  await page.setViewportSize({width:390,height:680});
  const startDate=page.locator('.project-goal-dates .date-trigger').first();
  await startDate.click();
  const calendar=page.getByRole('dialog',{name:'انتخاب تاریخ شروع پروژه',exact:true});
  await expect(calendar).toBeVisible();
  const calendarBounds=await calendar.boundingBox();
  expect(calendarBounds.x).toBeGreaterThanOrEqual(0);
  expect(calendarBounds.y).toBeGreaterThanOrEqual(0);
  expect(calendarBounds.x+calendarBounds.width).toBeLessThanOrEqual(390);
  expect(calendarBounds.y+calendarBounds.height).toBeLessThanOrEqual(680);
  await calendar.locator('.calendar-days button:not(.muted)').nth(12).click();
  await expect(calendar).toBeHidden();
  const chosenDate=await startDate.innerText();
  await page.locator('.project-goal-dates .date-trigger').last().click();
  const targetCalendar=page.getByRole('dialog',{name:'انتخاب تاریخ هدف پروژه',exact:true});
  await expect(targetCalendar).toBeVisible();
  await page.screenshot({path:path.join(screenshotDir,'project-wizard-calendar-mobile.png'),fullPage:false});
  await targetCalendar.locator('.calendar-days button:not(.muted)').nth(12).click({timeout:10000});
  await expect(page.locator('.project-goal-dates .date-trigger').last()).toHaveText(chosenDate);
  await expect(targetCalendar).toBeHidden();
  const modalBounds=await page.locator('.modal-panel').boundingBox();
  expect(modalBounds.x).toBeGreaterThanOrEqual(0);
  expect(modalBounds.x+modalBounds.width).toBeLessThanOrEqual(390);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({path:path.join(screenshotDir,'project-wizard-goal-mobile.png'),fullPage:false});
  await page.setViewportSize({width:1440,height:1000});
  await captureThemePair(page,screenshotDir,'project-wizard-goal');
  await dialog.getByRole('button',{name:'ساخت پروژه',exact:true}).click();
  await expect(dialog).toBeHidden();
  await captureThemePair(page,screenshotDir,'project-board-empty');
  await page.locator('.product-board .add-column-trigger').click();
  await page.locator('.product-board [data-inline-column-input]').fill('طراحی');
  await page.locator('.product-board .column-create-actions .primary').click();
  await expect(page.locator('.product-board .board-column')).toHaveCount(2);
  await captureThemePair(page,screenshotDir,'project-board');

  await page.goto('/tasks');
  await page.getByRole('button',{name:'افزودن ستون'}).first().click();
  await page.locator('.task-global-add-column .ui-select-trigger').click();
  await page.locator('.ui-select-option').filter({hasText:'بدون پروژه'}).click();
  await page.locator('[data-inline-column-input]').fill('در دست انجام');
  await page.locator('.column-create-actions .primary').click();
  await expect(page.locator('.task-stage-column')).toHaveCount(1);
  await captureThemePair(page,screenshotDir,'my-tasks-empty');
  await page.locator('.task-stage-column .add-column-task').click();
  await captureThemePair(page,screenshotDir,'task-create-modal');
  await dialog.getByPlaceholder('نام تسک').fill('کار آزمایشی');
  await dialog.getByRole('button',{name:'افزودن تسک',exact:true}).click();
  await expect(dialog).toBeHidden();
  await expect(page.locator('.task-card:visible')).toHaveCount(1);
  await page.locator('.snackbar-close').click();
  await captureThemePair(page,screenshotDir,'my-tasks-filled');
  await expect.poll(()=>contrastOf(page,'.sidebar-item.is-active')).toBeGreaterThanOrEqual(4.5);
  await expect.poll(()=>contrastOf(page,'.task-card')).toBeGreaterThanOrEqual(4.5);
  await page.locator('.task-card .task-title').click();
  await expect(dialog).toBeVisible();
  const autosaveResponse = page.waitForResponse(response => response.url().includes('/api/tasks/') && response.request().method()==='PUT');
  await dialog.getByPlaceholder('نام تسک',{exact:true}).fill('کار ویرایش‌شده');
  const savedResponse = await autosaveResponse;
  expect(await savedResponse.json()).toMatchObject({title:'کار ویرایش‌شده'});
  expect(savedResponse.ok()).toBeTruthy();
  await dialog.getByPlaceholder('توضیحات',{exact:true}).fill('توضیحات ویرایش‌شده');
  await dialog.getByRole('button',{name:'افزودن زیرتسک',exact:true}).click();
  await dialog.getByPlaceholder('عنوان زیرتسک',{exact:true}).fill('زیرتسک ذخیره‌شده');
  await expect.poll(async()=>page.evaluate(async()=>{
    const app=window.Alpine.$data(document.querySelector('.app-shell'));
    const saved=await app.api('/tasks/'+app.taskForm.id);
    return {description:saved.description,subtasks:saved.subtasks.map(s=>s.title)};
  })).toEqual({description:'توضیحات ویرایش‌شده',subtasks:['زیرتسک ذخیره‌شده']});
  await dialog.locator('.task-date-value').click();
  await page.locator('.task-date-popover .calendar-days button').nth(15).click();
  await expect.poll(async()=>page.evaluate(async()=>{
    const app=window.Alpine.$data(document.querySelector('.app-shell'));
    const saved=await app.api('/tasks/'+app.taskForm.id);
    return saved.due_date===app.taskForm.due_date;
  })).toBe(true);
  await page.locator('.task-comment-composer textarea').click();
  await captureThemePair(page,screenshotDir,'task-details-modal');
  await page.getByRole('button',{name:'بستن',exact:true}).last().click();
  await expect(dialog).toBeHidden();
  await page.locator('.sidebar-profile-button').click();
  await expect(page).toHaveURL(/\/settings$/);
  await expect(page.locator('.settings-panel')).toBeVisible();
  await page.goto('/tasks');
  await captureThemePair(page,screenshotDir,'sidebar-expanded');
  await page.locator('.sidebar-collapse-toggle').click();
  await expect(page.locator('.sidebar')).toHaveClass(/is-collapsed/);
  await captureThemePair(page,screenshotDir,'sidebar-collapsed');
  await page.locator('.sidebar-logo-toggle').click();
  await page.evaluate(()=>localStorage.setItem('rasta-theme','dark'));
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-theme','dark');
  await expect(page.locator('.task-card:visible')).toHaveCount(1,{timeout:10000});
  await page.waitForTimeout(400);
  await page.screenshot({path:path.join(screenshotDir,'tasks-dark.png'),fullPage:true});
  await page.evaluate(()=>localStorage.setItem('rasta-theme','light'));
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-theme','dark');
  await expect(page.locator('.task-card:visible')).toHaveCount(1,{timeout:10000});
  await page.waitForTimeout(400);
  await page.screenshot({path:path.join(screenshotDir,'tasks-light.png'),fullPage:true});

  const labelPalette=await page.evaluate(()=>window.Alpine.$data(document.querySelector('.app-shell')).labelColorPalette());
  expect(labelPalette).toEqual(Array.from({length:8},(_,index)=>`project-marker-${index+1}`));

  await page.goto('/settings');
  const avatarBounds=await page.locator('.settings-avatar').boundingBox();
  const initialsBounds=await page.locator('.settings-avatar .avatar-upload-initials').boundingBox();
  expect(initialsBounds.width).toBeCloseTo(avatarBounds.width,0);
  expect(initialsBounds.height).toBeCloseTo(avatarBounds.height,0);
  const accountName=page.locator('#settings-account-name');
  const originalName=await accountName.inputValue();
  await accountName.fill('نام موقت');
  await page.getByRole('button',{name:'لغو تغییرات',exact:true}).click();
  await expect(accountName).toHaveValue(originalName);
  await captureThemePair(page,screenshotDir,'settings-account');
  await expect(page.locator('.settings-nav').getByRole('button',{name:'ظاهر',exact:true})).toHaveCount(0);
  await expectSidebarHoverTokens(page);
  expect(await contrastOf(page,'.settings-panel')).toBeGreaterThanOrEqual(4.5);
  await page.evaluate(()=>localStorage.setItem('rasta-theme','light'));
  await page.emulateMedia({colorScheme:'light'});
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-theme','dark');
  await expect(page.locator('html')).toHaveAttribute('data-theme-preference','dark');

  await page.locator('.sidebar-collapse-toggle').click();
  await expect(page.locator('.sidebar')).toHaveClass(/is-collapsed/);
  await page.setViewportSize({width:390,height:844});
  await expect(page.locator('.sidebar')).toBeVisible();
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();
  expect(errors).toEqual([]);
});

test('profile avatar upload', async ({page}) => {
  const errors=[];
  page.on('pageerror', error => errors.push(error.message));
  await page.setViewportSize({width:1440,height:1000});
  await page.goto('/login');
  await page.locator('[name=pin]').fill(process.env.RASTA_TEST_MEMBER_PIN);
  await page.getByRole('button',{name:'ورود به فضای کار'}).click();
  if (await page.locator('.profile-choice').count()) {
    await page.locator('.profile-choice').first().click();
  } else {
    await page.locator('[name=name]').fill('عضو آزمون آواتار');
    await page.locator('[name=job_title]').fill('طراح');
    await page.getByRole('button',{name:'افزودن خودم',exact:true}).click();
  }
  await page.goto('/settings');
  const avatar=Buffer.from(await page.evaluate(()=>{const canvas=document.createElement('canvas');canvas.width=2;canvas.height=2;return canvas.toDataURL('image/png').split(',')[1];}),'base64');
  await expect(page.locator('.settings-avatar-change')).toHaveText('افزودن تصویر');
  await page.locator('[x-ref="settingsAvatarInput"]').setInputFiles({name:'avatar.png',mimeType:'image/png',buffer:avatar});
  await expect(page.locator('.settings-panel .settings-avatar img')).toHaveAttribute('src',/\/avatars\/\d+/,{timeout:30000});
  await expect(page.locator('[x-ref="settingsAvatarInput"]')).toBeEnabled({timeout:30000});
  await expect(page.locator('.settings-avatar-change')).toHaveText('تغییر پروفایل');
  const image=page.locator('.settings-panel .settings-avatar img');
  const firstUrl=await image.getAttribute('src');
  await expect.poll(()=>image.evaluate(img=>img.complete&&img.naturalWidth===512)).toBe(true);
  await page.locator('[x-ref="settingsAvatarInput"]').setInputFiles({name:'replacement.png',mimeType:'image/png',buffer:avatar});
  await expect.poll(()=>image.getAttribute('src')).not.toBe(firstUrl);
  await expect.poll(()=>image.evaluate(img=>img.complete&&img.naturalWidth===512)).toBe(true);
  await expect(page.locator('.settings-avatar')).toHaveCSS('border-top-width','0px');
  await expect(page.locator('.settings-avatar')).toHaveCSS('box-shadow','none');
  expect(errors).toEqual([]);
});

test('admin security settings in both themes', async ({page}) => {
  const screenshotDir=path.resolve('docs/color-audit/screenshots');
  fs.mkdirSync(screenshotDir,{recursive:true});
  await page.setViewportSize({width:1440,height:1000});
  await page.goto('/login');
  await page.evaluate(()=>localStorage.setItem('rasta-theme','dark'));
  await page.reload();
  await page.locator('.login-mode').getByRole('button',{name:'مدیر',exact:true}).click();
  await page.locator('[name=pin]').fill(process.env.RASTA_TEST_ADMIN_PIN);
  await page.getByRole('button',{name:'ورود به فضای کار'}).click();
  await expect(page.locator('.tasks-page')).toBeVisible();
  await page.goto('/settings');
  await page.locator('.settings-nav').getByRole('button',{name:'امنیت',exact:true}).click();
  await expect(page.getByRole('heading',{name:'امنیت و حریم خصوصی'})).toBeVisible();
  await captureThemePair(page,screenshotDir,'settings-security');
  await page.locator('.settings-security-row').nth(1).getByRole('button').click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await captureThemePair(page,screenshotDir,'settings-security-dialog');
});
