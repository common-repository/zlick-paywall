document.addEventListener('DOMContentLoaded', function setFadeColor() {
  const zw = document.getElementById('zlick-widget')
  var bgColor = window.getComputedStyle(document.body).getPropertyValue('background-color');
  if(!(document.getElementById('content') === null)){
    bgColor = window.getComputedStyle(document.getElementById('content')).getPropertyValue('background-color')
  }
  if(!(document.getElementById('primary') === null)){
    bgColor = window.getComputedStyle(document.getElementById('primary')).getPropertyValue('background-color')
  }

  const regex = /rgba?\((\d+\,\s?\d+\,\s?\d+)\,?\s?(\d+)?\)/

  if (!zw.style.getPropertyValue('--fade-bg-color')) {
    const matches = bgColor.match(regex)
    let fadeColor = matches[1]
    const alpha = matches.length === 3 ? matches[2] : null
    if (alpha && parseInt(alpha) === 0) {
      fadeColor = '255, 255, 255';
    }
    zw.style.setProperty('--fade-bg-color', fadeColor)
  }
})
