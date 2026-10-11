<?php
// Vexaris — SVG ikon seti (src/lib/data.tsx → Ic birebir karşılığı)
// Kullanım: echo vex_icon('cart', 'w-[18px] h-[18px]');

function vex_svg($cls, $inner, $vb = '0 0 24 24') {
  return '<svg viewBox="' . $vb . '" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="' . htmlspecialchars($cls ?: 'w-5 h-5') . '" aria-hidden="true">' . $inner . '</svg>';
}

function vex_icon($name, $cls = 'w-5 h-5') {
  switch ($name) {
    case 'cart': return vex_svg($cls, '<path d="M3 4h2.2l1.6 10.4a1.8 1.8 0 0 0 1.8 1.6h8.1a1.8 1.8 0 0 0 1.8-1.4L20 8H6.1"/><circle cx="9.5" cy="20" r="1.3"/><circle cx="16.5" cy="20" r="1.3"/>');
    case 'key': return vex_svg($cls, '<circle cx="8" cy="14" r="4"/><path d="M11 11l8-8M16 6l2.5 2.5M13.5 8.5L16 11"/>');
    case 'ticket': return vex_svg($cls, '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2.5a2 2 0 0 0 0 3V16a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2.5a2 2 0 0 0 0-3Z"/><path d="M13 6v2.5M13 11v2M13 15.5V18"/>');
    case 'book': return vex_svg($cls, '<path d="M5 4.5A2.5 2.5 0 0 1 7.5 2H19v17.5H7.5A2.5 2.5 0 0 0 5 22Z"/><path d="M5 19.5A2.5 2.5 0 0 1 7.5 17H19M9 7h6M9 10.5h4"/>');
    case 'plugin': return vex_svg($cls, '<path d="M9 3v4M15 3v4M7 7h10v4a5 5 0 0 1-5 5 5 5 0 0 1-5-5Z"/><path d="M12 16v5"/>');
    case 'box': return vex_svg($cls, '<path d="M12 2.8 20.5 7v10L12 21.2 3.5 17V7Z"/><path d="M3.5 7 12 11.4 20.5 7M12 11.4v9.8M7.8 4.9l8.5 4.4"/>');
    case 'server': return vex_svg($cls, '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01M11 7.5h2M11 16.5h2"/>');
    case 'shield': return vex_svg($cls, '<path d="M12 2.5 20 6v6c0 5-3.4 8.3-8 9.5C7.4 20.3 4 17 4 12V6Z"/><path d="m8.8 11.8 2.3 2.4 4.2-4.6"/>');
    case 'palette': return vex_svg($cls, '<path d="M12 21a9 9 0 1 1 9-9c0 2.5-1.7 3.6-3.6 3.6H15a2 2 0 0 0-1.5 3.3c.5.6.2 2.1-1.5 2.1Z"/><path d="M7.5 10.5h.01M11 7h.01M15.5 9.5h.01"/>');
    case 'mail': return vex_svg($cls, '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>');
    case 'users': return vex_svg($cls, '<circle cx="9" cy="8" r="3.4"/><path d="M3 20c.6-3.6 3-5.5 6-5.5s5.4 1.9 6 5.5M16 5.2a3.4 3.4 0 0 1 0 6.6M18 14.9c2 .7 3.3 2.4 3.7 5.1"/>');
    case 'chart': return vex_svg($cls, '<path d="M4 20V4M4 20h16"/><path d="M8 16v-5M12 16V7M16 16v-3M20 16V9"/>');
    case 'download': return vex_svg($cls, '<path d="M12 3v11M7.5 10 12 14.5 16.5 10"/><path d="M4 17v2.5A1.5 1.5 0 0 0 5.5 21h13a1.5 1.5 0 0 0 1.5-1.5V17"/>');
    case 'check': return vex_svg($cls, '<path d="m5 12.5 4.5 4.5L19 7.5"/>');
    case 'x': return vex_svg($cls, '<path d="M6 6l12 12M18 6 6 18"/>');
    case 'plus': return vex_svg($cls, '<path d="M12 5v14M5 12h14"/>');
    case 'minus': return vex_svg($cls, '<path d="M5 12h14"/>');
    case 'arrow': return vex_svg($cls, '<path d="M4 12h15M13 6l6 6-6 6"/>');
    case 'chev': return vex_svg($cls, '<path d="m6 9 6 6 6-6"/>');
    case 'copy': return vex_svg($cls, '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>');
    case 'lock': return vex_svg($cls, '<rect x="4.5" y="10" width="15" height="10.5" rx="2"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10M12 14.5v2"/>');
    case 'bolt': return vex_svg($cls, '<path d="M13 2 4.5 13.5H11L10 22l8.5-11.5H13Z"/>');
    case 'clock': return vex_svg($cls, '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>');
    case 'headset': return vex_svg($cls, '<path d="M4 13v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="13" width="4" height="6" rx="1.6"/><rect x="17" y="13" width="4" height="6" rx="1.6"/><path d="M19 19v.5a2.5 2.5 0 0 1-2.5 2.5H13"/>');
    case 'globe': return vex_svg($cls, '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.6 3.8 5.6 3.8 9S14.6 18.4 12 21c-2.6-2.6-3.8-5.6-3.8-9S9.4 5.6 12 3Z"/>');
    case 'wallet': return vex_svg($cls, '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18v3"/><rect x="3" y="7.5" width="18" height="12" rx="2"/><path d="M16.5 13.5h.01"/>');
    case 'coin': return vex_svg($cls, '<circle cx="12" cy="12" r="9"/><path d="M9.5 9h4a2 2 0 0 1 0 4h-4V7m0 6h4a2 2 0 0 1 0 4h-4v-4m-1-6h1m-1 10h1"/>');
    case 'discord': return vex_svg($cls, '<path d="M8.5 6.5C10 5.7 12 5.5 12 5.5s2 .2 3.5 1c2.6 1.4 3.6 4.6 3.6 8 0 0-1.6 2.3-5 2.9l-.7-1.4c-1 .3-2 .3-3 0l-.7 1.4c-3.4-.6-5-2.9-5-2.9 0-3.4 1-6.6 3.8-8Z"/><path d="M9.7 12.2h.01M14.3 12.2h.01"/>');
    case 'xlogo': return vex_svg($cls, '<path d="M5 4l14 16M19 4 5 20"/>');
    case 'github': return vex_svg($cls, '<circle cx="12" cy="12" r="9"/><path d="M12 17v-3.2c0-1 .4-1.6 1.2-2-2.4-.3-4-1.5-4-4 0-.8.2-1.5.6-2.1-.2-.7-.1-1.5.1-2 .8 0 1.6.4 2.2.9a7 7 0 0 1 3.8 0c.6-.5 1.4-.9 2.2-.9.2.5.3 1.3.1 2 .4.6.6 1.3.6 2.1 0 2.5-1.6 3.7-4 4 .8.4 1.2 1.1 1.2 2V17"/>');
    case 'spark': return vex_svg($cls, '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/>');
    case 'file': return vex_svg($cls, '<path d="M6 2.8h7L19 9v12.2H6Z"/><path d="M13 2.8V9h6M9 13h6M9 16.5h6"/>');
    case 'tag': return vex_svg($cls, '<path d="M3 11V4a1 1 0 0 1 1-1h7l10 10-8 8Z"/><path d="M7.5 7.5h.01"/>');
    case 'code': return vex_svg($cls, '<path d="m8 8-4 4 4 4M16 8l4 4-4 4"/>');
    case 'menu': return vex_svg($cls, '<path d="M4 7h16M4 12h16M4 17h10"/>');
    case 'layout': return vex_svg($cls, '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9.5h18M9.5 9.5V20"/>');
    default: return vex_svg($cls, '<circle cx="12" cy="12" r="9"/>');
  }
}
