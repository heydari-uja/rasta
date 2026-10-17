import {test, expect} from '@playwright/test';

async function contrastOf(page, selector) {
  return page.locator(selector).first().evaluate(element => {
    const {color, backgroundColor}=getComputedStyle(element);
    const channels=value=>value.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>v/255).map(v=>v<=0.04045?v/12.92:((v+0.055)/1.055)**2.4);
    const luminance=value=>channels(value).reduce((sum,v,i)=>sum+v*[0.2126,0.7152,0.0722][i],0);
    const a=luminance(color), b=luminance(backgroundColor);
    return (Math.max(a,b)+0.05)/(Math.min(a,b)+0.05);
  });
}

async function expectSidebarHoverTokens(page) {
  const expected = await page.evaluate(() => {
    const probe = document.createElement('span');
    probe.style.backgroundColor = 'var(--color-sys-bg-hover)';
    document.body.append(probe);
    const color = getComputedStyle(probe).backgroundColor;
    probe.remove();
    return color;
  });
  for (const selector of ['.user-account', '.sidebar-notification-button']) {
    const control = page.locator(selector).first();
    await control.hover();
    await expect(control).toHaveCSS('background-color', expected);
  }
  const projectRow = page.locator('.project-shortcut-wrap').first();
  await projectRow.hover();
  const editButton = projectRow.locator('.project-edit-btn');
  await expect(editButton).toBeVisible();
  await editButton.hover();
  await expect(editButton).toHaveCSS('background-color', expected);
}

test('workspace flows and resolved color themes', async ({page}) => {
  const errors=[];
  page.on('pageerror', error => errors.push(error.message));
  await page.setViewportSize({width:1440,height:1000});
  await page.goto('/login');
  await expect(page.locator('html')).toHaveAttribute('data-theme',/^(light|dark)$/);
  await page.locator('[name=password]').fill(process.env.RASTA_TEST_PASSWORD);
  await page.getByRole('button',{name:'ورود به فضای کار'}).click();
  await expect(page.locator('html')).toHaveAttribute('data-theme',/^(light|dark)$/);
  await expect(page.getByRole('heading',{name:'شما کی هستید؟'})).toBeVisible();
  await page.locator('[name=name]').fill('عضو آزمون');
  await page.locator('[name=job_title]').fill('طراح');
  await page.getByRole('button',{name:'افزودن خودم',exact:true}).click();
  await expect(page.locator('.tasks-page')).toBeVisible();
  await expect(page.getByRole('heading',{name:'هنوز مرحله‌ای ندارید'})).toBeVisible();

  await page.getByRole('button',{name:'ساخت اولین پروژه'}).click();
  const dialog=page.getByRole('dialog');
  const projectColorToken=await page.locator('html').evaluate(element=>getComputedStyle(element).getPropertyValue('--color-sys-data-project-default').trim());
  await expect(dialog.locator('input[type="color"]')).toHaveValue(projectColorToken);
  await dialog.locator('[x-model="projectForm.name"]').fill('پروژه آزمون رابط');
  await dialog.locator('[x-model="projectForm.description"]').fill('یک مسیر روشن برای همکاری تیم');
  await dialog.getByRole('button',{name:'ادامه',exact:true}).click();
  await dialog.locator('[x-model="projectForm.goal.title"]').fill('رسیدن به نتیجه قابل سنجش');
  await dialog.locator('[x-model="projectForm.goal.success_criterion"]').fill('تکمیل مسیر تعریف شده');
  await dialog.getByRole('button',{name:'ادامه',exact:true}).click();
  await dialog.getByRole('button',{name:'افزودن مرحله',exact:true}).click();
  await dialog.getByPlaceholder('نام مرحله',{exact:true}).fill('طراحی');
  await dialog.getByRole('button',{name:'ساخت پروژه',exact:true}).click();
  await expect(dialog).toBeHidden();
  await expect(page.locator('.product-board .board-column')).toHaveCount(2);

  await page.goto('/tasks');
  await page.getByRole('button',{name:'افزودن ستون'}).first().click();
  await page.locator('.task-global-add-column .ui-select-trigger').click();
  await page.locator('.ui-select-option').filter({hasText:'بدون پروژه'}).click();
  await page.locator('[data-inline-column-input]').fill('در دست انجام');
  await page.locator('.column-create-actions .primary').click();
  await expect(page.locator('.task-stage-column')).toHaveCount(1);
  await page.locator('.task-stage-column .add-column-task').click();
  await dialog.getByPlaceholder('نام تسک').fill('کار آزمایشی');
  await dialog.getByRole('button',{name:'افزودن تسک',exact:true}).click();
  await expect(dialog).toBeHidden();
  await expect(page.locator('.task-card:visible')).toHaveCount(1);
  expect(await contrastOf(page,'.sidebar nav a.active')).toBeGreaterThanOrEqual(4.5);
  expect(await contrastOf(page,'.task-card')).toBeGreaterThanOrEqual(4.5);

  await page.goto('/labels');
  await page.getByRole('button',{name:/افزودن برچسب/}).click();
  const firstLabelColor=page.locator('.label-color-choice').first();
  const labelColorToken=await page.evaluate(()=>{const probe=document.createElement('span');probe.style.color='var(--color-sys-data-label-palette-1)';document.body.append(probe);const color=getComputedStyle(probe).color;probe.remove();return color;});
  await expect(firstLabelColor.locator('span')).toHaveCSS('background-color',labelColorToken);
  await expect(page.locator('.label-color-choice')).toHaveCount(12);

  await page.goto('/settings');
  await page.getByRole('button',{name:'روشن',exact:true}).click();
  await expect(page.locator('html')).toHaveAttribute('data-theme','light');
  await expectSidebarHoverTokens(page);
  expect(await contrastOf(page,'.settings-panel')).toBeGreaterThanOrEqual(4.5);
  expect(await contrastOf(page,'.settings-panel .button.primary')).toBeGreaterThanOrEqual(4.5);
  await expect(page.locator('.settings-panel .button.primary')).toHaveCSS('color','rgb(255, 255, 255)');
  await expect(page.locator('.theme-options button.selected')).toHaveAttribute('aria-pressed','true');
  const light=await page.locator('body').evaluate(el=>getComputedStyle(el).backgroundColor);
  await page.getByRole('button',{name:'تاریک',exact:true}).click();
  await expect(page.locator('html')).toHaveAttribute('data-theme','dark');
  await expectSidebarHoverTokens(page);
  expect(await contrastOf(page,'.settings-panel')).toBeGreaterThanOrEqual(4.5);
  expect(await contrastOf(page,'.settings-panel .button.primary')).toBeGreaterThanOrEqual(4.5);
  await expect(page.locator('.settings-panel .button.primary')).toHaveCSS('color','rgb(255, 255, 255)');
  expect(await page.locator('.theme-settings').evaluate(el=>getComputedStyle(el).backgroundColor)).not.toBe(await page.locator('.theme-options').evaluate(el=>getComputedStyle(el).backgroundColor));
  const dark=await page.locator('body').evaluate(el=>getComputedStyle(el).backgroundColor);
  expect(dark).not.toBe(light);
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-theme','dark');
  await page.getByRole('button',{name:'سیستم',exact:true}).click();
  await expect(page.locator('html')).toHaveAttribute('data-theme-preference','system');
  await expect(page.locator('html')).toHaveAttribute('data-theme',/^(light|dark)$/);

  await page.setViewportSize({width:390,height:844});
  await expect(page.getByRole('button',{name:'باز کردن منو'})).toBeVisible();
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();
  expect(errors).toEqual([]);
});

test('profile avatar upload and removal', async ({page}) => {
  const errors=[];
  page.on('pageerror', error => errors.push(error.message));
  await page.setViewportSize({width:1440,height:1000});
  await page.goto('/login');
  await page.locator('[name=password]').fill(process.env.RASTA_TEST_PASSWORD);
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
  await page.locator('.avatar-settings input[type="file"]').setInputFiles({name:'avatar.png',mimeType:'image/png',buffer:avatar});
  await expect(page.locator('.settings-panel .account-heading img')).toHaveAttribute('src',/\/avatars\/\d+/,{timeout:30000});
  await expect(page.locator('.avatar-settings input[type="file"]')).toBeEnabled({timeout:30000});
  await page.getByRole('button',{name:'حذف تصویر پروفایل',exact:true}).click();
  await expect(page.locator('.settings-panel .account-heading img')).toBeHidden();
  await expect(page.locator('.toast')).toContainText('عکس پروفایل حذف شد');
  expect(errors).toEqual([]);
});
