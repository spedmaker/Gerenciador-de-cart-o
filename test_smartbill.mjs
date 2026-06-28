import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
page.setDefaultTimeout(15000);

// 1. Dashboard (empty state)
await page.goto('http://localhost:8000/');
await page.screenshot({ path: '/tmp/claude-1000/-home-user-cartao/9388e866-d34d-4139-8385-92fb55fe0367/scratchpad/01_dashboard_empty.png', fullPage: true });
console.log('1. Dashboard:', await page.title());

// 2. Seed category rules
await page.goto('http://localhost:8000/category-rules');
await page.screenshot({ path: '/tmp/claude-1000/-home-user-cartao/9388e866-d34d-4139-8385-92fb55fe0367/scratchpad/02_rules_empty.png', fullPage: true });
await page.click('button:has-text("Carregar padrões")');
await page.waitForURL(/category-rules/);
await page.screenshot({ path: '/tmp/claude-1000/-home-user-cartao/9388e866-d34d-4139-8385-92fb55fe0367/scratchpad/03_rules_seeded.png', fullPage: true });
console.log('2. Rules seeded');

// 3. Import PDF
await page.goto('http://localhost:8000/statements/create');
await page.screenshot({ path: '/tmp/claude-1000/-home-user-cartao/9388e866-d34d-4139-8385-92fb55fe0367/scratchpad/04_import_form.png', fullPage: true });
const [fileChooser] = await Promise.all([
  page.waitForEvent('filechooser'),
  page.click('input[type=file]')
]);
await fileChooser.setFiles('/home/user/cartao/statement_month_26_06_2026_14_23_13.pdf');
await page.click('button[type=submit]');
await page.waitForURL(/statements\/[a-f0-9]+/, { timeout: 30000 });
await page.screenshot({ path: '/tmp/claude-1000/-home-user-cartao/9388e866-d34d-4139-8385-92fb55fe0367/scratchpad/05_statement_detail.png', fullPage: true });
console.log('3. Statement imported, URL:', page.url());

// 4. Dashboard with data
await page.goto('http://localhost:8000/');
await page.screenshot({ path: '/tmp/claude-1000/-home-user-cartao/9388e866-d34d-4139-8385-92fb55fe0367/scratchpad/06_dashboard_with_data.png', fullPage: true });
console.log('4. Dashboard with data');

// 5. Uncategorized
await page.goto('http://localhost:8000/transactions/uncategorized');
await page.screenshot({ path: '/tmp/claude-1000/-home-user-cartao/9388e866-d34d-4139-8385-92fb55fe0367/scratchpad/07_uncategorized.png', fullPage: true });
console.log('5. Uncategorized page');

await browser.close();
console.log('DONE');
