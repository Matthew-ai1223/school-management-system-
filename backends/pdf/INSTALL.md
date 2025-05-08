# DOMPDF Installation Guide

This directory contains the PDF receipt generation functionality for the student registration system. It uses the DOMPDF library to generate beautiful PDF receipts.

## Installation Steps

1. Navigate to the `backends/pdf` directory in your terminal:
   ```
   cd backends/pdf
   ```

2. Install dompdf using Composer:
   ```
   composer install
   ```
   
   If you don't have Composer installed, you can download it from [getcomposer.org](https://getcomposer.org/download/).
   
3. Alternative Manual Installation:
   If you can't use Composer, you can manually download and extract the dompdf library:
   
   a. Download the latest version from [github.com/dompdf/dompdf/releases](https://github.com/dompdf/dompdf/releases)
   b. Extract the files to the `backends/pdf/dompdf` directory
   c. Make sure the directory structure looks like:
      ```
      backends/pdf/dompdf/autoload.inc.php
      backends/pdf/dompdf/src/...
      ```

## Troubleshooting

- If you encounter permission issues when generating PDFs, make sure your web server has write permissions to this directory.
- If images are not showing in the PDF, ensure the `isRemoteEnabled` option is set to true and that your server allows remote connections.

## Features

- Generates professionally formatted PDF receipts
- Includes school logo and all student registration information
- Displays admission/registration number prominently
- Includes parent/guardian information and login credentials 