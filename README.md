# USA Tax Provider for Shopware

A powerful and privacy-focused sales tax automation plugin designed specifically for U.S. merchants using the Shopware e-commerce platform. Stay compliant with ever-changing U.S. tax obligations—across states, counties, cities, and even special taxing districts—without exposing your sensitive store data to third-party services.

---

## 🇺🇸 Overview

This plugin brings seamless, automated U.S. sales tax compliance to your Shopware installation. It calculates and applies the correct combination of state, county, city, and special district sales taxes in real time, based on each customer's shipping address and your business’s nexus locations. Compliant tax rates can also be configured for shipping fees.

**Key Privacy Feature:**  
All tax calculations are performed locally. No data is sent to third-party APIs, ensuring total control and security for your Shopware environment.

---

## ✨ Features

- Automatic tax calculation for all 50 states and thousands of local jurisdictions
- Multi-tier rate support: state, county, city, special taxing districts
- Full compliance with destination-based U.S. tax sourcing rules
- Supports tax-exempt customers and products
- Accurate tax calculation for every line item and shipping fee
- Instant rate updates: simply edit the JSON tax rate file in the plugin folder
- No reliance on external tax APIs — your data stays private
- Detailed tax breakdowns in backend order details and customer invoices
- Modern Shopware 6.6+ compatibility

---

## 🛠️ Requirements

- Shopware 6.6.0 or newer
- PHP 8.2 or newer
- Business registration in the United States (for lawful use)
- Suitable for merchants selling to U.S. customers

---

## 📦 Installation

1. Download the plugin from the Shopware Store or your account.
2. Activate the plugin in the Shopware admin backend.

## ⚙️ Configuration

1. Go to **Settings > Extensions > USA Tax Provider for Shopware** in your Shopware backend.
2. Assign the appropriate tax rates to your products and shipping methods.
3. For tax-exempt customers or products, configure the respective exemption settings.

**Note:** When tax rates change, administrators can manually update the rates by editing the easy-to-read `TaxRates-US.json` file located in the plugin directory.

---

## 🎯 Usage

### For Store Administrators

- Instantly see tax details in every order’s summary and line items
- Export complete tax reports by state, county, city, and special taxing district for easy returns filing
- Update rates at any time—no waiting for API vendors or risking privacy

### For Customers
- Accurate, transparent tax calculation at checkout based on their shipping address
- Clearly itemized tax components visible on invoices and order confirmations
- No delays or errors due to external service outages

---

## 🌐 Supported Languages

- English (en-US)

---

## 📊 Tax Coverage

- All 50 states plus Washington D.C.
- Local rates for over 12,000 U.S. jurisdictions (counties, cities, special districts)
- Automatic selection and application based on shipping address
- Out-of-box support for states with no general sales tax (AK, DE, MT, NH, OR) with configurable local options

---

## ⚠️ Tax Compliance Notice

*While this plugin automates U.S. sales tax calculations and compliance, it remains your responsibility as a merchant to verify and file accurate returns for all applicable federal, state, and local tax laws. Please consult a qualified tax professional for any complex or high-volume sales scenarios.*

---

## 🔄 Changelog

**v1.0.0 – 2025-07-25**
- Initial release with nationwide multi-jurisdiction tax support
- Comprehensive backend reporting
- Manual local rate editing via JSON for instant compliance
- Shopware 6.6+ compatibility

---

## 🤝 Contributing

This is proprietary software.  
Contributions and feature requests should be submitted via email or our Shopware Store support channel.

---

**Made with ❤️ for U.S. online businesses**

*For questions about this plugin, contact our support team at iecsp.com@gmail.com.*