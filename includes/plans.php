<?php
// plans.php — fiyatlandirma tek kaynagi (index.php vitrin + pricing.php detay)
// Degerler: [etiket, deger] — deger true/false (var/yok) ya da metin.
$PLANS = [
  ['slug'=>'free','name'=>'Free','icon'=>'spark','desc'=>'Core features to get started.','popular'=>false,
   'price_m'=>'$0','price_y'=>'$0','bill_m'=>'free forever','bill_y'=>'free forever','cta'=>'Start for free',
   'feats'=>[
    ['Sales commission','0%'],['Products','7'],['Revenue','$100'],['Traffic','50 GB'],['Hosting',true],['Subdomain',true],['Custom domain',false],
    ['Automatic delivery',true],['Payment integrations',true],['Coupon system',true],['Support tickets',true],
    ['Permission system',false],['Team',false],['Vexaris AI',false],['Themes','1'],['Module system',false],['API',false],['Support','Basic'],
  ]],
  ['slug'=>'starter','name'=>'Starter','icon'=>'bolt','desc'=>'The ideal start for small projects.','popular'=>false,
   'price_m'=>'$6.99','price_y'=>'$5.59','bill_m'=>'per month, billed monthly','bill_y'=>'per month, billed yearly ($67.10)','cta'=>'Choose Starter',
   'feats'=>[
    ['Sales commission','0%'],['Products','35'],['Revenue','$1,000'],['Traffic','Unlimited'],['Hosting',true],['Subdomain',true],['Custom domain',true],
    ['Automatic delivery',true],['Payment integrations',true],['Coupon system',true],['Support tickets',true],
    ['Permission system','Basic'],['Team','3'],['Vexaris AI','Limited'],['Themes','2'],['Module system','Basic'],['API',false],['Support','Standard'],
  ]],
  ['slug'=>'professional','name'=>'Professional','icon'=>'box','desc'=>'An advanced set for growing businesses.','popular'=>true,
   'price_m'=>'$14.99','price_y'=>'$11.99','bill_m'=>'per month, billed monthly','bill_y'=>'per month, billed yearly ($143.90)','cta'=>'Choose Professional',
   'feats'=>[
    ['Sales commission','0%'],['Products','75'],['Revenue','$5,000'],['Traffic','Unlimited'],['Hosting',true],['Subdomain',true],['Custom domain',true],
    ['Automatic delivery',true],['Payment integrations',true],['Coupon system',true],['Support tickets',true],
    ['Permission system','Advanced'],['Team','10'],['Vexaris AI','Standard'],['Themes','3'],['Module system','Advanced'],['API',true],['Support','Priority'],
  ]],
  ['slug'=>'enterprise','name'=>'Enterprise','icon'=>'shield','desc'=>'Full coverage for custom needs.','popular'=>false,
   'price_m'=>'$39.99','price_y'=>'$31.99','bill_m'=>'per month, billed monthly','bill_y'=>'per month, billed yearly ($383.90)','cta'=>'Choose Enterprise',
   'feats'=>[
    ['Sales commission','0%'],['Products','Unlimited'],['Revenue','Unlimited'],['Traffic','Unlimited'],['Hosting',true],['Subdomain',true],['Custom domain',true],
    ['Automatic delivery',true],['Payment integrations',true],['Coupon system',true],['Support tickets',true],
    ['Permission system','Advanced'],['Team','30+'],['Vexaris AI','Wide Limit'],['Themes','All'],['Module system','Advanced'],['API',true],['Support','24/7 Premium'],
  ]],
];
$PLAN_GROUPS = [
  'Limits' => ['Sales commission','Products','Revenue','Traffic'],
  'Store' => ['Hosting','Subdomain','Custom domain','Automatic delivery','Payment integrations','Coupon system','Support tickets','Themes','Module system','Vexaris AI','API'],
  'Team' => ['Permission system','Team'],
  'Support' => ['Support'],
];
$PLAN_FAQS = [
  ['q'=>'Can I upgrade or downgrade later?','a'=>'Yes. You can switch plans at any time from your panel. Upgrades apply immediately; downgrades take effect at the end of the current billing period. Your products, orders and settings stay untouched.'],
  ['q'=>'What counts toward the revenue limit?','a'=>'The revenue limit is the total completed sales volume on your store within the billing period. When you approach the limit we notify you in the panel so you can upgrade before sales are affected.'],
  ['q'=>'How does the traffic limit work?','a'=>'The Free plan includes 50 GB of bandwidth per month. Every paid plan — Starter, Professional and Enterprise — includes unlimited bandwidth, so a large file, a course or a plugin download never stops a sale. Usage is still shown in your panel. Unlimited bandwidth is subject to a fair-use policy against abusive patterns such as bot floods.'],
  ['q'=>'How do custom domains work?','a'=>'On paid plans you point your domain to your store with a DNS record and confirm it in the panel. Your subdomain keeps working as an alias, so existing links never break.'],
  ['q'=>'Can I cancel anytime?','a'=>'Yes. Monthly plans can be cancelled in one click and stay active until the period ends. Yearly plans are refunded pro-rata within the first 14 days. There are no setup fees and no lock-in.'],
  ['q'=>'Is Vexaris AI included in every plan?','a'=>'Vexaris AI starts with a limited quota on Starter, standard usage on Professional and a wide monthly limit on Enterprise. It helps you write product descriptions, announcements and SEO fields, and answers questions about your own store data.'],
];
function plan_feat_val(array $plan, string $label) {
  foreach ($plan['feats'] as $f) {
    if ($f[0] === $label) return $f[1];
  }
  return null;
}
