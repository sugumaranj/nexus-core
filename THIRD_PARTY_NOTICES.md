# Third-Party Software Notices and Attribution

This document contains attribution and licensing notices for third-party software components, libraries, and assets incorporated into or used by **NexusCore**.

---

## 1. Overview & Compliance Notice

NexusCore is proprietary software created by Sugumaran J. The platform utilizes open-source libraries under permissive and weak copyleft licenses (including MIT, Apache-2.0, BSD-3-Clause, ISC, and LGPL-2.1 / LGPL-3.0).

In compliance with the attribution and copyright requirements of these licenses:
- All original copyright notices, permission notices, and disclaimers are preserved.
- The original license files are maintained within their respective distribution directories (`vendor/` for PHP packages and `node_modules/` for JavaScript packages).
- Dynamic linking / autoloading is used for LGPL components without modifying their upstream source code.

---

## 2. PHP Dependencies (Composer)

The following backend libraries are managed via Composer and located in the `vendor/` directory:

| Package | Version | License (SPDX) | Authors / Copyright Holders | Upstream Repository / Source |
| :--- | :--- | :--- | :--- | :--- |
| `vlucas/phpdotenv` | 5.8.x | `BSD-3-Clause` | Graham Campbell, Vance Lucas | https://github.com/vlucas/phpdotenv |
| `dompdf/dompdf` | 3.1.x | `LGPL-2.1` | The Dompdf Community | https://github.com/dompdf/dompdf |
| `phpmailer/phpmailer` | 7.2.x | `LGPL-2.1-only` | Marcus Bointon, Jim Jagielski, Andy Prevost, Brent R. Matzelle | https://github.com/PHPMailer/PHPMailer |
| `setasign/fpdf` | 1.9.x | `MIT` | Olivier Plathey | http://www.fpdf.org |
| `setasign/fpdi` | 2.6.x | `MIT` | Jan Slabon, Maximilian Kresse (Setasign) | https://www.setasign.com/fpdi |
| `chillerlan/php-qrcode` | 6.0.x | `MIT` / `Apache-2.0` | Kazuhiko Arase, ZXing Authors, Ashot Khanamiryan, Smiley | https://github.com/chillerlan/php-qrcode |
| `nelexa/zip` | 4.1.x | `MIT` | Ne-Lexa (Alexey) | https://github.com/Ne-Lexa/php-zip |
| `smalot/pdfparser` | 2.13.x | `LGPL-3.0` | Sebastien Malot | https://www.pdfparser.org |
| `dompdf/php-font-lib` | 1.0.x | `LGPL-2.1-or-later` | The FontLib Community | https://github.com/dompdf/php-font-lib |
| `dompdf/php-svg-lib` | 1.0.x | `LGPL-3.0-or-later` | The SvgLib Community | https://github.com/dompdf/php-svg-lib |
| `masterminds/html5` | 2.12.x | `MIT` | Matt Butcher, Matt Farina, Asmir Mustafic | https://github.com/Masterminds/html5-php |
| `sabberworm/php-css-parser` | 9.5.x | `MIT` | Raphael Schweikert, Oliver Klee, Jake Hotson | https://github.com/MyIntervals/PHP-CSS-Parser |
| `chillerlan/php-settings-container` | 3.3.x | `MIT` | Smiley | https://github.com/chillerlan/php-settings-container |
| `phpoption/phpoption` | 1.10.x | `Apache-2.0` | Johannes M. Schmitt, Graham Campbell | https://github.com/schmittjoh/php-option |
| `graham-campbell/result-type` | 1.2.x | `MIT` | Graham Campbell | https://github.com/GrahamCampbell/Result-Type |
| `psr/http-message` | 2.0.x | `MIT` | PHP-FIG (PHP Framework Interop Group) | https://github.com/php-fig/http-message |
| `symfony/finder` | 8.1.x | `MIT` | Fabien Potencier, Symfony Community | https://github.com/symfony/finder |
| `symfony/polyfill-*` | 1.x | `MIT` | Nicolas Grekas, Gert de Pagter, Ion Bazan, Symfony Community | https://github.com/symfony/polyfill |
| `phpunit/phpunit` *(Dev)* | 13.4.x | `BSD-3-Clause` | Sebastian Bergmann | https://phpunit.de |

---

## 3. Frontend & Node.js Dependencies (NPM)

The following frontend libraries are managed via NPM and located in `node_modules/` or bundled in `public/assets/`:

| Package | Version | License (SPDX) | Authors / Copyright Holders | Upstream Repository / Source |
| :--- | :--- | :--- | :--- | :--- |
| `bootstrap` | 5.3.x | `MIT` | The Bootstrap Authors, Twitter, Inc. | https://getbootstrap.com |
| `bootstrap-icons` | 1.13.x | `MIT` | The Bootstrap Authors | https://icons.getbootstrap.com |
| `chart.js` | 4.6.x | `MIT` | Chart.js Contributors | https://www.chartjs.org |
| `html2canvas` | 1.4.x | `MIT` | Niklas von Hertzen | https://html2canvas.hertzen.com |
| `marked` | 18.2.x | `MIT` | Christopher Jeffrey | https://marked.js.org |
| `md-to-pdf` | 5.2.x | `MIT` | Simon Haenisch | https://github.com/simonhaenisch/md-to-pdf |
| `pako` | 3.0.x | `MIT` AND `Zlib` | Vitaly Puzrin, Andrei Tuputcyn | https://github.com/nodeca/pako |
| `pdfjs-dist` | 6.5.x | `Apache-2.0` | Mozilla Foundation | https://mozilla.github.io/pdf.js |
| `puppeteer-core` | 25.17.x | `Apache-2.0` | The Chromium Authors | https://github.com/puppeteer/puppeteer |
| `tesseract.js` | 7.0.x | `Apache-2.0` | Jerome Wu and Tesseract.js Contributors | https://github.com/naptha/tesseract.js |
| `@tesseract.js-data/eng` | 1.0.x | `MIT` / `Apache-2.0` | Naptha / ScribeOCR | https://github.com/naptha/tessdata |

---

## 4. Vendored Assets & Web Resources

The following client-side libraries are bundled in `public/assets/` or served via CDN:

| Component | Asset Location / CDN URL | License (SPDX) | Copyright Holders |
| :--- | :--- | :--- | :--- |
| **Flatpickr** | `public/assets/js/vendor/flatpickr.min.js`<br>`public/assets/css/flatpickr.min.css` | `MIT` | Gregory Petrosyan |
| **PDF.js Worker** | `public/assets/vendor/ocr/pdf.min.mjs`<br>CDN (`cdnjs.cloudflare.com`) | `Apache-2.0` | Mozilla Foundation |
| **Tesseract Core WASM** | `public/assets/vendor/ocr/core/*` | `Apache-2.0` | Google Inc. / Naptha Contributors |
| **Interact.js** | CDN (`jsdelivr.net`) | `MIT` | Taye Adeyemi |
| **Inter Font** | Google Fonts (`fonts.googleapis.com`) | `OFL-1.1` | Rasmus Andersson |

---

## 5. Component Attribution & Copyright Notices

### MIT Licensed Components
*Applicable to: Bootstrap, Bootstrap Icons, Chart.js, Flatpickr, HTML2Canvas, Marked, Md-to-pdf, FPDF, FPDI, Nelexa Zip, Masterminds HTML5, PHP CSS Parser, Symfony Components, Interact.js.*

> Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
>
> The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
>
> THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

---

### Apache License 2.0 Components
*Applicable to: Tesseract.js, PDF.js (`pdfjs-dist`), Puppeteer-Core, phpoption, chillerlan/php-qrcode (dual).*

> Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License. You may obtain a copy of the License at:
> https://www.apache.org/licenses/LICENSE-2.0
>
> Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.

---

### BSD 3-Clause Components
*Applicable to: `vlucas/phpdotenv`, `phpunit/phpunit`.*

> Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
> 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
> 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
> 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
>
> THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.

---

### GNU Lesser General Public License (LGPL v2.1 & v3.0)
*Applicable to: `dompdf/dompdf` (LGPL-2.1), `phpmailer/phpmailer` (LGPL-2.1-only), `smalot/pdfparser` (LGPL-3.0), `dompdf/php-font-lib` (LGPL-2.1-or-later), `dompdf/php-svg-lib` (LGPL-3.0-or-later).*

> NexusCore dynamically interfaces with these libraries via standard Composer autoloading mechanisms (`vendor/autoload.php`) without modifying their source code. The unmodified upstream source code and license files are available in the respective `vendor/` directories, and users may replace or update these libraries independently.
>
> Copies of the GNU Lesser General Public License may be viewed locally at:
> - `vendor/dompdf/dompdf/LICENSE.LGPL`
> - `vendor/phpmailer/phpmailer/LICENSE`
> - `vendor/smalot/pdfparser/LICENSE.txt`
>
> Or online at:
> - LGPL 2.1: https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html
> - LGPL 3.0: https://www.gnu.org/licenses/lgpl-3.0.html
