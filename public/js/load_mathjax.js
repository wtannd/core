window.MathJax = {
  tex: {
    // Enable $...$ for inline math, $$...$$ for block math
    inlineMath: [['$', '$'], ['\\(', '\\)']],
    displayMath: [['$$', '$$'], ['\\[', '\\]']],
    processEscapes: true // Allows users to type \$ to render a literal dollar sign
  },
  options: {
    // Tell MathJax to ignore math inside elements with these classes (e.g., code blocks or raw inputs)
    ignoreHtmlClass: 'tex2jax_ignore|mathjax_ignore|form-*',
    processHtmlClass: 'tex2jax|mathjax|doc-.*title|doc-.*abstract'
  },
  svg: {
    fontCache: 'global' // Optimizes SVG rendering performance
  }
};

(function () {
  var script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/mathjax@4/tex-svg.js';
  script.defer = true;
  document.head.appendChild(script);
})();
