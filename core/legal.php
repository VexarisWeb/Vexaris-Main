<?php
require_once __DIR__ . '/db.php';

function legal_slugs(): array {
    return ['terms-of-use', 'privacy-policy', 'cookie-policy'];
}

function legal_titles(): array {
    return [
        'terms-of-use'   => 'Terms of Use',
        'privacy-policy' => 'Privacy Policy',
        'cookie-policy'  => 'Cookie Policy',
    ];
}

function legal_slug_valid(string $slug): bool {
    return in_array($slug, legal_slugs(), true);
}

function legal_defaults(): array {
    $d = [];

    $d['terms-of-use'] = <<<'HTML'
<div class="legal__toc">
    <p>Contents</p>
    <a href="#k1">1. General Provisions</a>
    <a href="#k2">2. Site Use and Declaration of Acceptance</a>
    <a href="#k3">3. Membership and Account Security</a>
    <a href="#k4">4. Intellectual Property Rights</a>
    <a href="#k5">5. Use of Licensed Software</a>
    <a href="#k6">6. User Obligations and Prohibitions</a>
    <a href="#k7">7. Suspension and Termination of Service</a>
    <a href="#k8">8. Limits of Liability</a>
    <a href="#k9">9. Garanti Verilmemesi</a>
    <a href="#k10">10. Third Party Links</a>
    <a href="#k11">11. Governing Law and Competent Courts</a>
    <a href="#k12">12. Dispute Resolution</a>
    <a href="#k13">13. Changes and Notices</a>
    <a href="#k14">14. Miscellaneous Provisions</a>
    <a href="#k15">15. Communication</a>
    <a href="#k16">16. Service Description (SaaS Infrastructure)</a>
    <a href="#k17">17. Content and Domain Responsibility</a>
    <a href="#k18">18. Law Applicable to SaaS Services</a>
    <a href="#k19">19. Access Blocking and Termination in Official Requests</a>
</div>

<div class="legal__sect" id="k1">
    <h2>1. General Provisions</h2>
    <p>These Terms of Use (&quot;Terms&quot;); It regulates the relationship between the company operating under the Vexaris brand (&quot;Vexaris&quot; or &quot;Site&quot;) and natural and legal persons (&quot;User&quot;) who access or use the Site via the website www.vexaris.net.</p>
    <p>By accessing or using the Site, the User is deemed to have read, understood and agreed to be bound by these Terms. Every User who continues to use the Site declares that he/she has accepted these Terms in full and complete.</p>
    <p>If any provision of these Terms is not accepted, the User must immediately stop using the Site. It is definitively agreed that if the User continues to use the Site, he/she will be deemed to have accepted the aforementioned provisions.</p>
</div>

<div class="legal__sect" id="k2">
    <h2>2. Site Use and Declaration of Acceptance</h2>
    <p>The User agrees to use the Site only for lawful purposes and in accordance with these Terms, and undertakes not to use the services offered on the site in a manner contrary to law, morality, public order and the rights of third parties.</p>
    <p>The User accepts and declares compliance with the relevant contract and license provisions regarding the use of all software, licenses, services and content purchased or obtained through the site.</p>
    <p>All or part of the Site may be temporarily or permanently suspended, changed, rearranged or terminated without prior notice. Vexaris cannot be held responsible for any damages that may arise in this context.</p>
</div>

<div class="legal__sect" id="k3">
    <h2>3. Membership and Account Security</h2>
    <p>In order to access the sections of the Site that require membership, the User must register with accurate, complete and up-to-date information. The user is personally responsible for the accuracy of the information he provides.</p>
    <p>The user is obliged to maintain the confidentiality of his/her username, password and other access information. The User is responsible for all transactions carried out through the User&#039;s account, and all consequences arising from the use of the account by third parties belong to the User.</p>
    <p>If unauthorized use of access information is noticed, the User is obliged to immediately notify Vexaris. Otherwise, Vexaris cannot be held responsible for data losses, unauthorized transactions and other damages that may occur.</p>
</div>

<div class="legal__sect" id="k4">
    <h2>4. Intellectual Property Rights</h2>
    <p>All software, code, design, graphics, logos, text, visuals, audio, videos and all other content on the Site, as well as their compilation and arrangement, are protected under copyright, trademark rights, patent rights, industrial design rights and intellectual and industrial property rights legislation.</p>
    <p>These contents may not be used without the prior written consent of Vexaris or the relevant rights holders. Such content cannot be copied, reproduced, modified, published, distributed, sold, transferred, or used for commercial purposes in any way, in whole or in part.</p>
    <p>The right to use the Site granted to the User by these Terms does not constitute a transfer of intellectual property rights on the Site in any way. Use of the Site does not create any ownership rights over the relevant content.</p>
</div>

<div class="legal__sect" id="k5">
    <h2>5. Use of Licensed Software</h2>
    <p>Users who acquire paid and/or free versions of the Vexaris platform may use the relevant software only within the conditions and scope specified in the purchase/license agreement. The principles regarding the use of the software are separately regulated in the relevant License Agreement.</p>
    <p>The software; It is prohibited to copy, modify, reverse engineer, redistribute, sublicense, sell, rent or make available to third parties in any way the source code, in whole or in part. In case of violation of these prohibitions, Vexaris has the right to terminate the relevant license immediately and without prejudice to its liability for compensation.</p>
    <p>License verification is made through technical queries automatically made to the license server from the server on which the software is installed. If the license expires or verification fails, some or all features of the software may become unusable.</p>
</div>

<div class="legal__sect" id="k6">
    <h2>6. User Obligations and Prohibitions</h2>
    <p>The User accepts and undertakes not to perform the following acts while using the Site:</p>
    <ul>
        <li>Violating, disrupting or disrupting the security of the Site or systems accessed through the Site,</li>
        <li>Preventing the operation of the Site, overloading it or disabling it (including DoS/DDoS attacks),</li>
        <li>Using or distributing malicious software, viruses, worms, trojan horses or similar codes on the site,</li>
        <li>Attempting to gain unauthorized access to other users&#039; accounts, data or information,</li>
        <li>Publishing content that is unlawful, obscene, insulting, hate speech or that violates the rights of third parties on the site,</li>
        <li>Sending spam, chain letters, mass e-mails or similar unsolicited content through the site,</li>
        <li>Copying, duplicating or changing the codes, design or content of the Site without permission,</li>
        <li>Attempting to circumvent, disable or manipulate license protection mechanisms.</li>
    </ul>
    <p>In case of violation of any of the above prohibitions, Vexaris has the right to suspend or terminate the User&#039;s account, prevent his/her access to the Site, take legal action and compensate for the damages incurred.</p>
</div>

<div class="legal__sect" id="k7">
    <h2>7. Suspension and Termination of Service</h2>
    <p>If the User violates these Terms, Vexaris may temporarily or permanently suspend or terminate the User&#039;s account and prevent his/her access to the Site without prior notice.</p>
    <p>The user can terminate the membership agreement at any time by closing his account. In case of termination, the User&#039;s accounts, licenses and other rights lose their validity, without prejudice to the relevant contractual provisions.</p>
    <p>If the account is suspended or terminated, the content and data created by the User on the Site may be deleted by Vexaris, excluding legal retention obligations. Vexaris is not under any liability for compensation in this context.</p>
</div>

<div class="legal__sect" id="k8">
    <h2>8. Limits of Liability</h2>
    <p>Vexaris; It does not guarantee that the Site will operate uninterrupted, error-free, secure and complete. Access to the Site may be temporarily blocked or interrupted due to technical difficulties, maintenance work, internet connection problems, natural disasters and force majeure events.</p>
    <p>Vexaris cannot be held responsible for any direct, indirect, incidental, special or punitive damages (loss of data, loss of profits, business interruption, etc.) arising from the use of the Site.</p>
    <p>Vexaris is not responsible for third-party services, products, content accessed through the site and the performance of these services. The user carries out his/her relations with third party services under his/her own responsibility.</p>
</div>

<div class="legal__sect" id="k9">
    <h2>9. Garanti Verilmemesi</h2>
    <p>The Site and all products, services and content offered through the Site<strong>&quot;as is&quot;</strong> ve <strong>&quot;as it exists&quot;</strong>is offered. Vexaris makes no warranties, express or implied, regarding the fitness for a particular purpose, merchantability, quality, accuracy and reliability of these products and services.</p>
    <p>The user is obliged to evaluate the suitability of products and services for his or her needs before using them. Vexaris cannot be held responsible for any damages that may occur as a result of incorrect or incorrect use.</p>
</div>

<div class="legal__sect" id="k10">
    <h2>10. Third Party Links</h2>
    <p>The Site may contain links to third party websites and services. These links are provided solely for the convenience of the User and Vexaris has no control over the content, privacy policies and terms of use of the relevant sites.</p>
    <p>Access to third party sites is entirely at the User&#039;s own risk. Vexaris cannot be held responsible for the content or accuracy of these sites or any damages arising from the use of these sites.</p>
</div>

<div class="legal__sect" id="k11">
    <h2>11. Governing Law and Competent Courts</h2>
    <p>The provisions of these Terms regarding the use of the Site, membership, store and marketplace services are subject to the legislation of the Republic of Türkiye. In disputes within this scope, the laws of the Republic of Türkiye apply and the courts and enforcement offices of the place where Vexaris&#039; head office is located are authorized.</p>
    <p>In terms of provisions regarding SaaS infrastructure/hosting services, Article 18 of these Terms applies; The distinction in scope between the two articles is essential.</p>
</div>

<div class="legal__sect" id="k12">
    <h2>12. Dispute Resolution</h2>
    <p>In case of disputes arising from these Terms, the User reserves the remedies within the scope of the Law on Consumer Protection No. 6502, the Law on the Regulation of Electronic Commerce No. 6563 and the relevant legislation.</p>
    <p>The parties may try compromise and alternative resolution methods before resorting to litigation. However, exhausting these means is not a prerequisite for exercising legal rights.</p>
</div>

<div class="legal__sect" id="k13">
    <h2>13. Changes and Notices</h2>
    <p>Vexaris has the right to unilaterally change these Terms at any time, with or without prior notice. Changes become effective as soon as they are published on the Site.</p>
    <p>The User is obliged to regularly follow the current version of these Terms. The User who continues to use the Site after the changes come into force is deemed to have accepted such changes.</p>
</div>

<div class="legal__sect" id="k14">
    <h2>14. Miscellaneous Provisions</h2>
    <p>If any provision of these Terms is determined to be invalid, unlawful or unenforceable, such provision will be severed and evaluated accordingly; The remaining provisions remain in full force and effect.</p>
    <p>Vexaris&#039; failure to exercise any of its rights under these Terms will not constitute a waiver of that right. Failure to exercise rights does not constitute an obstacle to their use in the future.</p>
    <p>The User may not transfer its rights and obligations under these Terms without the prior written consent of Vexaris. Vexaris may transfer its rights and obligations without any restrictions.</p>
</div>

<div class="legal__sect" id="k15">
    <h2>15. Communication</h2>
    <p>Your questions, comments and requests regarding these Terms<strong>info@vexaris.net</strong>at or on the Site<a href="mailto:destek@vexaris.net">contact form</a>You can send it via .</p>
</div>

<div class="legal__sect" id="k16">
    <h2>16. Service Description (SaaS Infrastructure)</h2>
    <p>Vexaris is not a hosting company. Vexaris is automatically running infrastructure/SaaS software developed to enable users to manage their own content and sell digital products.</p>
    <p>Vexaris provides the User with software functions such as store infrastructure, management panel, payment integrations, licensing, delivery automation and support tools. The nature of the service offered is software service, and it does not include commitment to server rental or hosting service in the classical sense.</p>
    <p>SaaS infrastructure servers are hosted abroad. Self-host setup is not public; Available only upon special request and with Vexaris approval.</p>
</div>

<div class="legal__sect" id="k17">
    <h2>17. Content and Domain Responsibility</h2>
    <p>The User who is the owner of the relevant domain name is solely responsible for all kinds of texts, images, products, digital content and publications published through the custom domain names (Custom Domain) that the users connect to the system.</p>
    <p>In case of detection of copyright violations, unauthorized content use, illegal product sales or illegal activities, the relevant User&#039;s account will be closed immediately without any excuse. No refunds will be made for accounts closed within this scope.</p>
</div>

<div class="legal__sect" id="k18">
    <h2>18. Law Applicable to SaaS Services</h2>
    <p>The provisions of these Terms relating to SaaS infrastructure/hosting services (including clauses 16, 17 and 19) are governed by the laws of the United States of America. (Information about the state where the company is established will be added.)</p>
    <p>In disputes within this scope, the competent courts of the relevant US state have jurisdiction. This article does not cover the Site/membership/marketplace services regulated in Article 11; Moreover, it does not eliminate the mandatory provisions of the current legislation.</p>
</div>

<div class="legal__sect" id="k19">
    <h2>19. Access Blocking and Termination in Official Requests</h2>
    <p>In case of an access blocking decision, legal complaint or official request by local official institutions (including BTK, courts, cybercrime units) regarding the User&#039;s site, the domain name or infrastructure usage, Vexaris has the right to unilaterally, without compensation and immediately suspend the relevant User&#039;s account and infrastructure access.</p>
    <p>The User cannot claim any refund, compensation or rights from Vexaris due to the suspension operations carried out in this context. Logs and records required within the scope of legal obligations can be shared with the competent authorities in accordance with the legislation.</p>
</div>

<p class="legal__updated">These Terms of Use may be changed unilaterally by Vexaris. The current version is always published on the Site.</p>
HTML;

    $d['privacy-policy'] = <<<'HTML'
<div class="legal__toc">
    <p>Contents</p>
    <a href="#g1">1. Veri Sorumlusu</a>
    <a href="#g2">2. Processed Personal Data</a>
    <a href="#g3">3. Purposes of Processing Personal Data</a>
    <a href="#g4">4. Conditions of Processing of Personal Data and Legal Reasons</a>
    <a href="#g5">5. Methods of Collection of Personal Data</a>
    <a href="#g6">6. Transfer of Personal Data</a>
    <a href="#g7">7. Storage and Deletion of Personal Data</a>
    <a href="#g8">8. Data Security</a>
    <a href="#g9">9. Cookies</a>
    <a href="#g10">10. Rights of the Relevant Person</a>
    <a href="#g11">11. Application Methods</a>
    <a href="#g12">12. Changes</a>
    <a href="#g13">13. Logging and Forensic Sharing</a>
    <a href="#g14">14. Third Party Infrastructure and DNS Deployment</a>
    <a href="#g15">15. Data Ownership and End User Data</a>
    <a href="#g16">16. Data Transfer Abroad</a>
</div>

<div class="legal__sect" id="g1">
    <h2>1. Veri Sorumlusu</h2>
    <p>Vexaris is the data controller in terms of personal data processing activities carried out through the website (&quot;Site&quot;) accessed under the domain name www.vexaris.net. All natural persons who visit, review, use or interact with the Site (&quot;Relevant Person&quot;) are considered within the scope of this Privacy Policy.</p>
    <p>This Privacy Policy; It regulates the procedures and principles regarding the processing of all personal data shared by the Relevant Person through the Site or obtained automatically during the use of the Site. Unless otherwise stated in the Policy, all transactions made on the Site are subject to this Policy.</p>
</div>

<div class="legal__sect" id="g2">
    <h2>2. Processed Personal Data</h2>
    <p>Your personal data categorized below may be processed on the site:</p>
    <ul>
        <li><strong>Kimlik Bilgileri:</strong>Name, surname, username.</li>
        <li><strong>Contact Information:</strong>Email address, telephone number, residence/delivery address.</li>
        <li><strong>Membership and Account Information:</strong>Username, password (hashed), account preferences, membership date.</li>
        <li><strong>Transaction Security Information:</strong>IP address, session information, login and logout times, device and browser information used.</li>
        <li><strong>Finansal Bilgiler:</strong>Invoice information and payment information regarding purchases (without prejudice to special legal regulations).</li>
        <li><strong>Legal Transaction Information:</strong>Information regarding legal requests, complaints, and processes carried out with judicial and administrative authorities.</li>
        <li><strong>Request/Complaint Management Information:</strong>Information you submit through contact forms and support requests.</li>
    </ul>
    <p>Processing of the above information that is not mandatory for the operation of the Site is subject to your express consent. If mandatory information is not provided, you may not be able to benefit from some services of the Site.</p>
</div>

<div class="legal__sect" id="g3">
    <h2>3. Purposes of Processing Personal Data</h2>
    <p>Your personal data is processed for the following purposes:</p>
    <ul>
        <li>Creating membership registration, account management and carrying out identity verification processes,</li>
        <li>Performance of the products and services offered by the Site, management of orders and payments,</li>
        <li>Carrying out invoicing and accounting processes, fulfilling legal obligations,</li>
        <li>Receiving, evaluating and finalizing your requests and complaints,</li>
        <li>Monitoring the use of the Site and performing performance and security analyses,</li>
        <li>Carrying out communication and marketing activities (only with your explicit consent),</li>
        <li>Resolving legal disputes and providing information to the competent authorities,</li>
        <li>Performing software updates, license verification and technical support activities.</li>
    </ul>
</div>

<div class="legal__sect" id="g4">
    <h2>4. Conditions of Processing of Personal Data and Legal Reasons</h2>
    <p>Your personal data is processed based on one or more of the following legal reasons listed in Article 5 of the KVKK:</p>
    <ul>
        <li>It is clearly stipulated in the law,</li>
        <li>The obligation to protect the life or physical integrity of the person or another person who is unable to express his/her consent due to actual impossibility or whose consent is not legally valid,</li>
        <li>It is necessary to process personal data of the parties to the contract, provided that it is directly related to the establishment or performance of a contract,</li>
        <li>It is mandatory for the data controller to fulfill its legal obligation,</li>
        <li>The data has been made public by the relevant person,</li>
        <li>Data processing is mandatory for the establishment, exercise or protection of a right,</li>
        <li>It is mandatory to process data for the legitimate interests of the data controller, provided that it does not harm the fundamental rights and freedoms of the person concerned.</li>
    </ul>
    <p>In cases where none of the above legal reasons exist, your personal data will be processed only with your explicit consent. You can withdraw your express consent at any time; Withdrawal of consent does not affect the legality of previously performed transactions.</p>
</div>

<div class="legal__sect" id="g5">
    <h2>5. Methods of Collection of Personal Data</h2>
    <p>Your personal data; Registration forms, contact forms, cookies and similar technical mechanisms on the site are collected electronically through automatic registration systems and third-party service providers.</p>
    <p>Collected data; As long as it is up-to-date, accurate and complete, it is processed in accordance with the relevant legislation. The Relevant Person is obliged to ensure that the information he shares is up to date.</p>
</div>

<div class="legal__sect" id="g6">
    <h2>6. Transfer of Personal Data</h2>
    <p>Your personal data; In accordance with the conditions stipulated in Articles 8 and 9 of the KVKK, it can be transferred to the following persons and organizations:</p>
    <ul>
        <li>Business partners and suppliers for the purpose of providing services,</li>
        <li>Software hosting and server service providers,</li>
        <li>Payment institutions and banks (within the scope of purchasing transactions),</li>
        <li>Authorized public institutions, administrative authorities and courts (in cases of legal obligation),</li>
        <li>Companies providing audit, legal and consultancy services.</li>
    </ul>
    <p>Data transfer abroad; It is carried out only if you have your explicit consent, in countries with an adequate level of protection, or if one of the conditions of obtaining a written commitment is met.</p>
</div>

<div class="legal__sect" id="g7">
    <h2>7. Storage and Deletion of Personal Data</h2>
    <p>Your personal data; It is stored for the period necessary for the purposes of processing and within the framework of the retention periods stipulated in the legal legislation. If the storage period expires or the purpose of processing no longer exists, your data will be deleted, destroyed or anonymized.</p>
    <p>The periods determined within the scope of legal retention obligations (tax, trade, accounting legislation, etc.) are subject to the provisions of the relevant legislation.</p>
</div>

<div class="legal__sect" id="g8">
    <h2>8. Data Security</h2>
    <p>Vexaris, in order to protect your personal data; It takes the necessary technical and administrative measures in accordance with Article 12 of the KVKK to prevent unauthorized access, unlawful processing, disclosure, alteration or destruction.</p>
    <p>In this context; Measures such as encrypting data, applying access controls, keeping log records, using firewalls and intrusion detection systems, and imposing confidentiality obligations on personnel and service providers are implemented.</p>
    <p>Data transmission over the Internet cannot be guaranteed to be completely secure. However, Vexaris takes all reasonable technical measures to protect your data and constantly updates these measures.</p>
</div>

<div class="legal__sect" id="g9">
    <h2>9. Cookies</h2>
    <p>Cookies and similar technologies are used on the site to improve your usage experience and increase service quality. Detailed information on the use of cookies<a href="cookie-policy">Our Cookie Policy</a>You can reach via .</p>
</div>

<div class="legal__sect" id="g10">
    <h2>10. Rights of the Relevant Person</h2>
    <p>In accordance with Article 11 of the KVKK, you have the following rights as a Relevant Person:</p>
    <ul>
        <li>Learning whether your personal data is being processed or not,</li>
        <li>If it has been processed, requesting information about it,</li>
        <li>Learning the purpose of processing your personal data and whether it is used in accordance with its purpose,</li>
        <li>Knowing the third parties to whom your personal data is transferred at home or abroad,</li>
        <li>Request correction of your personal data if it is incomplete or incorrectly processed,</li>
        <li>Requesting the deletion or destruction of your personal data within the framework of the conditions stipulated in Article 7 of the KVKK,</li>
        <li>Requesting correction and/or deletion to be notified to third parties to whom personal data has been transferred,</li>
        <li>Object to the emergence of a result against you due to the analysis of the processed data exclusively by automatic systems,</li>
        <li>Request compensation for the damage if you suffer damage due to unlawful processing of your personal data.</li>
    </ul>
</div>

<div class="legal__sect" id="g11">
    <h2>11. Application Methods</h2>
    <p>You can submit your applications regarding your rights listed above in writing or by other methods determined by the Board in accordance with Article 13 of the KVKK.<strong>info@vexaris.net</strong>address or on the Site.<a href="mailto:destek@vexaris.net">contact form</a>You can send it via .</p>
    <p>Your application will be finalized free of charge within thirty (30) days at the latest, depending on the nature of the request. If the application incurs processing costs, a fee may be charged based on the tariff determined by the Board. Your application must be clear and understandable and include your identity and authority information.</p>
</div>

<div class="legal__sect" id="g12">
    <h2>12. Changes</h2>
    <p>This Privacy Policy may be updated by Vexaris at any time in line with legal regulations and technological developments. The updated policy comes into force as soon as it is published on the Site. You can always access the current version on the Site.</p>
</div>

<div class="legal__sect" id="g13">
    <h2>13. Logging and Forensic Sharing</h2>
    <p>In order to ensure system and network security and to fulfill legal obligations; IP addresses, entry-exit timestamps and system logs from which users access the system are recorded and stored for the periods stipulated in the legislation.</p>
    <p>In case of an official and duly request from legal authorities (courts, prosecutor&#039;s offices, BTK and relevant administrative units), the relevant logs and records may be shared with judicial authorities in accordance with legal obligation. Logs are not shared with third parties without official request.</p>
</div>

<div class="legal__sect" id="g14">
    <h2>14. Third Party Infrastructure and DNS Deployment</h2>
    <p>Cloudflare infrastructure is used for customers&#039; private domain redirects and Site traffic. In this process, data passes through the Cloudflare network for network security, attack prevention and traffic optimization purposes.</p>
    <p>Data passing through Cloudflare may also be subject to the relevant third party&#039;s own privacy policies. Vexaris does not share your data with third parties except for mandatory infrastructure transfers.</p>
</div>

<div class="legal__sect" id="g15">
    <h2>15. Data Ownership and End User Data</h2>
    <p>Vexaris; does not sell, market or process for its own benefit the end user data (including end user emails, order histories and similar store data) collected by its customers in its stores.</p>
    <p>The store owner (customer) is solely responsible for the security of this database, its legality and obtaining the necessary permissions from the relevant persons. Vexaris only accesses store data to the extent necessary for technical maintenance, security and legal obligations.</p>
</div>

<div class="legal__sect" id="g16">
    <h2>16. Data Transfer Abroad</h2>
    <p>Since SaaS infrastructure servers are hosted abroad, your personal data may be transferred abroad. These transfers are within the scope of Article 9 of Law No. 6698. Transfers are carried out based on your express consent and/or adequate protection commitments and appropriate assurances prescribed by the Board.</p>
    <p>By using the Site, you agree to hosting on overseas servers and transferring through infrastructure providers such as Cloudflare. Technical and administrative measures are taken to protect the transferred data; You can submit your requests using the application methods specified in Article 11.</p>
</div>

<p class="legal__updated">This Privacy Policy may be changed unilaterally by Vexaris. The current version is always published on the Site.</p>
HTML;

    $d['cookie-policy'] = <<<'HTML'
<div class="legal__toc">
    <p>Contents</p>
    <a href="#c1">1. Purpose and Scope</a>
    <a href="#c2">2. What is a Cookie?</a>
    <a href="#c3">3. Types of Cookies Used</a>
    <a href="#c4">4. Purposes of Use of Cookies</a>
    <a href="#c5">5. Cookie Management and User Controls</a>
    <a href="#c6">6. Third Party Cookies</a>
    <a href="#c7">7. Data Transfer</a>
    <a href="#c8">8. Storage Periods and Cookie List</a>
    <a href="#c9">9. Your rights</a>
    <a href="#c10">10. Communication</a>
</div>

<div class="legal__sect" id="c1">
    <h2>1. Purpose and Scope</h2>
    <p>The purpose of this Cookie Policy is; operating under the Vexaris brand and<a href="index.php">www.vexaris.net</a>To inform the relevant persons about the personal data processing activities carried out by Vexaris, which is the data controller, regarding the cookies and other similar technologies used on the website accessed through the domain name and all sub-pages, applications, portals and affiliated services accessible through this site (together referred to as the &quot;Site&quot;).</p>
    <p>This policy; It applies to all natural person users (&quot;User&quot; or &quot;Relevant Person&quot;) who visit, review, use or interact with the Site in any way, and all legal and natural persons who purchase or intend to purchase services through the Site.</p>
    <p>The principles regarding the protection of personal data processed through cookies used on the Site are published separately on the Site.<a href="privacy-policy">Privacy Policy</a> ve <a href="terms-of-use">Terms of Use</a>should be evaluated together with This policy forms an integral part of the said documents.</p>
</div>

<div class="legal__sect" id="c2">
    <h2>2. What is a Cookie?</h2>
    <p>Cookies are small data files, usually consisting of letters and numbers, that are downloaded and stored on your device (computer, smartphone, tablet or other internet access device) when you visit a website. Cookies are stored on your device through your browser and transmitted back to the Site by your browser when you access the Site again.</p>
    <p>Cookies are used to enable the Site to perform its basic functions, improve your experience on the Site, remember your preferences and improve service quality by analyzing your use of the Site. Cookies also play an important role in maintaining your session securely and preventing unauthorized access.</p>
    <p>Except for cookies; Pixel tags, web beacons, local storage, session storage, fingerprint identification and similar techniques will also be referred to with the concept of &quot;cookie&quot; and will be evaluated within the scope of this policy.</p>
</div>

<div class="legal__sect" id="c3">
    <h2>3. Types of Cookies Used</h2>
    <p>The cookies used on the site are classified in detail below. This classification is made in terms of the storage period of cookies, their domain names and functions:</p>
    <h3>3.1. Cookies by Storage Duration</h3>
    <ul>
        <li><strong>Session Cookies:</strong>These are cookies that expire when you close the browser and are valid throughout the session. It ensures that your navigation within the site continues safely and uninterruptedly.</li>
        <li><strong>Persistent Cookies:</strong>These are cookies that are stored on your device for a certain period of time and are reactivated each time you visit. It ensures that your preferences and login information are remembered.</li>
    </ul>
    <h3>3.2. Cookies According to Their Domain Name</h3>
    <ul>
        <li><strong>First Party Cookies:</strong>These are cookies placed directly on your device by the site.</li>
        <li><strong>Third Party Cookies:</strong>These are cookies placed on your device by third parties upon the guidance of the Site, as part of the content and services offered on the Site.</li>
    </ul>
    <h3>3.3. Cookies According to Purposes of Use</h3>
    <ul>
        <li><strong>Mandatory / Technical Cookies:</strong>Absolutely necessary for the operation of the Site; These are cookies that provide login, security, load balancing and basic functionality. When these cookies are disabled, some functions of the Site cannot be performed.</li>
        <li><strong>Functional / Preference Cookies:</strong>These are cookies that personalize your Site experience by remembering your user preferences such as language selection, region selection, appearance preferences.</li>
        <li><strong>Performance/Analytical Cookies:</strong>These are cookies used to measure the site&#039;s usage statistics, number of visitors, which pages are visited more frequently and visit durations. Thanks to these cookies, the performance of the Site is improved.</li>
        <li><strong>Marketing / Advertising Cookies:</strong>These are cookies that track your visiting habits to ensure that you are shown content and advertisements tailored to your interests. These cookies are only used with your explicit consent.</li>
    </ul>
</div>

<div class="legal__sect" id="c4">
    <h2>4. Purposes of Use of Cookies</h2>
    <p>The cookies used on the site are used for the following purposes:</p>
    <ul>
        <li>Fulfilling the basic functions of the Site completely and securely,</li>
        <li>Managing user sessions and preventing unauthorized access,</li>
        <li>Remembering user preferences (language, theme, appearance, etc.),</li>
        <li>Collecting statistical data regarding site usage and measuring performance,</li>
        <li>Improving the services offered on the site and developing new services,</li>
        <li>Fulfilling legal obligations and meeting sharing requirements with competent authorities,</li>
        <li>Providing user-specific content and marketing communications (only with express consent).</li>
    </ul>
</div>

<div class="legal__sect" id="c5">
    <h2>5. Cookie Management and User Controls</h2>
    <p>In accordance with Article 5 of the Personal Data Protection Law No. 6698, a separate express consent is not required for cookies whose processing is mandatory to provide the service, and the legal basis for the use of these cookies is based on the legal reason that &quot;it is necessary to process personal data of the parties to the contract, provided that it is directly related to the establishment or performance of a contract&quot;.</p>
    <p>Cookies other than mandatory cookies (functional, performance and marketing) are used only with your explicit consent. In the cookie notice displayed on your first visit to the Site<strong>"Kabul Et"</strong>You can approve all of them with<strong>"Reddet"</strong>You can continue with only mandatory cookies or<strong>&quot;Other Options&quot;</strong>You can choose based on category. The pre-checked box is not used; The choice is entirely yours.</p>
    <p>You can change your cookie preferences at any time in one of the following ways:</p>
    <ul>
        <li>You can block, delete or reject third-party cookies through your browser&#039;s settings menu,</li>
        <li>You can manage cookie notifications by adjusting the privacy settings of your browser,</li>
        <li>You can prevent your visit history from being stored by using private/private browsing modes.</li>
    </ul>
    <p>If cookies are blocked or deleted, some parts of the Site may not function properly; In particular, disabling session cookies may prevent you from logging in and performing transactions that require membership. The Site cannot be held responsible for any usage restrictions arising from this situation.</p>
</div>

<div class="legal__sect" id="c6">
    <h2>6. Third Party Cookies</h2>
    <p>On the site; Cookies of third party providers may be used to provide measurement, analysis, maps and similar services. The privacy policies of the relevant providers are decisive as to which data these third-party providers process, for what purpose and for how long.</p>
    <p>Major third-party services include:<strong>Cloudflare</strong>(for bot protection and security verification<em>__cf_bm</em> ve <em>cf_clearance</em>cookies; security verification is done via Cloudflare Turnstile),<strong>Google Analytics</strong>(only if you provide analytics consent and the relevant measurement ID is defined<em>_ga</em>cookies; IP anonymization is active). Cookies from these services will not be installed unless you consent to the relevant category.</p>
    <p>You can access detailed information about third-party cookies and the management of these cookies through the cookie and privacy policies of the relevant providers. The site is not directly responsible for the processing activities carried out by third-party providers in accordance with their own policies.</p>
</div>

<div class="legal__sect" id="c7">
    <h2>7. Data Transfer</h2>
    <p>Personal data obtained through cookies; It can be transferred within the country, with the service providers and business partners of the data controller, in accordance with the conditions stipulated in Article 8 of the KVKK. Transfer of data obtained through the use of cookies abroad is only possible if you have your explicit consent or one of the exceptions listed in Article 9 of the KVKK exists.</p>
</div>

<div class="legal__sect" id="c8">
    <h2>8. Storage Periods and Cookie List</h2>
    <p>Cookies are stored on your device for different periods of time depending on their type. Session cookies expire when the browser is closed, while persistent cookies are stored for a specified period of time (usually up to 12 months). When personal data ceases to be relevant to the purpose for which they are processed, it is automatically deleted, destroyed or anonymized by the data controller.</p>
    <p>The main records used on the Site are:</p>
    <ul>
        <li><strong>vexaris_sess:</strong>Session cookie (first-party, mandatory, HttpOnly). Resumes your login session; It expires when the browser is closed or after 180 days.</li>
        <li><strong>remember_token:</strong>&quot;Remember me&quot; cookie (first-party, mandatory). It is created only if you check it; Valid for 180 days, stored with hash.</li>
        <li><strong>vex-cookie-consent-v2:</strong>Your cookie preferences (browser local storage, technically not cookies). Stored for 1 year; If you choose to reject, only the &quot;all off&quot; information is kept.</li>
        <li><strong>__cf_bm / cf_clearance:</strong>Cloudflare bot and security verification (third party, mandatory). Lives in the order of minutes-days for session/bot score.</li>
        <li><strong>_ga and its derivatives:</strong>Google Analytics (third party, analytics). It will only be loaded if you consent and the measurement ID is defined; IP is anonymized.</li>
    </ul>
</div>

<div class="legal__sect" id="c9">
    <h2>9. Your rights</h2>
    <p>In accordance with Article 11 of the KVKK, you have the right to learn whether your personal data has been processed, to request information if it has been processed, to learn the purpose of processing and whether it is used in accordance with its purpose, to know the third parties to whom it has been transferred domestically or abroad, to request correction if it has been processed incompletely or incorrectly, to request its deletion or destruction, to request correction/deletion to be notified to third parties to whom it has been transferred, to object to automatic processing that results in adverse results, and to request compensation for damages due to unlawful processing.</p>
    <p>To exercise these rights, you must:<strong>Communication</strong>You can contact us through the channels in the section. Your applications will be finalized within thirty (30) days at the latest, depending on the nature of the request.</p>
</div>

<div class="legal__sect" id="c10">
    <h2>10. Communication</h2>
    <p>Contact us for your questions and requests about our cookie policy.<strong>info@vexaris.net</strong>at or on the Site<a href="mailto:destek@vexaris.net">contact form</a>You can contact via. Your applications will be answered as soon as possible within the scope of KVKK and relevant legislation.</p>
</div>

<p class="legal__updated">This Cookie Policy may be changed unilaterally by Vexaris. The current version is always published on the Site.</p>
HTML;

    $d['mesafeli-satis'] = <<<'HTML'
<div class="legal__toc">
    <p>Contents</p>
    <a href="#m1">1. Taraflar</a>
    <a href="#m2">2. Subject Matter and Scope</a>
    <a href="#m3">3. Product, Delivery and License</a>
    <a href="#m4">4. Payment and Invoicing</a>
    <a href="#m5">5. Right of Withdrawal and Digital Exemption</a>
    <a href="#m6">6. Return and Cancellation Conditions</a>
    <a href="#m7">7. Dispute and Competent Authorities</a>
</div>

<div class="legal__sect" id="m1">
    <h2>1. Taraflar</h2>
    <p>This Distance Selling Agreement (&quot;Agreement&quot;) was concluded between Vexaris (&quot;Seller&quot;), which provides digital products and SaaS infrastructure services, on the one hand, and the natural or legal person (&quot;Buyer&quot;) who creates orders through www.vexaris.net, on the other hand, within the framework of the provisions of the Consumer Protection Law No. 6502 and the Distance Contracts Regulation.</p>
    <p>Before confirming the order, the Buyer declares electronically that he has read, understood and accepted this Agreement and the preliminary information text. An order without approval cannot be created.</p>
</div>

<div class="legal__sect" id="m2">
    <h2>2. Subject Matter and Scope</h2>
    <p>The subject of this Agreement; It is the sale and delivery of digital products (e-pins, license keys, software packages, downloadable files and similar intangible goods and SaaS subscriptions) ordered electronically by the Buyer from the Seller and determining the rights and obligations of the parties regarding them.</p>
    <p>The products subject to the contract are not subject to physical delivery; Delivery is made electronically, by identification to the Buyer&#x27;s panel account.</p>
</div>

<div class="legal__sect" id="m3">
    <h2>3. Product, Delivery and License</h2>
    <p>The qualities, scope and conditions of use of each product ordered are also stated on the product page. Delivery is reflected in the relevant section of the Buyer&#x27;s panel (file download, license key assignment or subscription activation) immediately following payment confirmation, as a rule, within 24 hours at the latest.</p>
    <p>Digital products are delivered with a single user license unless stated otherwise on the product page. Sharing, resale or public distribution of license keys with third parties is prohibited; If detected, the license may be canceled without compensation.</p>
</div>

<div class="legal__sect" id="m4">
    <h2>4. Payment and Invoicing</h2>
    <p>Payments; It is received through the payment infrastructures offered on the Site, including Shopier, PayTR, iyzico, Stripe, PayPal, BitPay, Cryptomus, Paywant and Whop. Card and account information is not stored on Vexaris servers; Collection is made with the assurance of the relevant payment institution.</p>
    <p>The order price is charged on the total amount including taxes and invoicing is carried out in accordance with the applicable tax legislation.</p>
</div>

<div class="legal__sect" id="m5">
    <h2>5. Right of Withdrawal and Digital Exemption</h2>
    <p>In accordance with the Distance Contracts Regulation, the Buyer has the right to withdraw without giving any reason within 14 (fourteen) days as a rule.</p>
    <p><strong>Important exception:</strong>The right of withdrawal cannot be exercised for services performed instantly in electronic environment and intangible goods delivered instantly to the Buyer (such as downloaded file, assigned license key, activated subscription), provided that the Buyer accepts the express consent and notification that he will lose his right of withdrawal. This issue is also submitted for your approval during the ordering step.</p>
    <p>The right of withdrawal is reserved for orders that have not yet been delivered; The fee is refunded without interruption.</p>
</div>

<div class="legal__sect" id="m6">
    <h2>6. Return and Cancellation Conditions</h2>
    <p>For digital products that are not delivered or delivered defective, the Buyer; may request the re-delivery of the product, replacement with an equivalent product or a refund. The defect claim is reported together with the support request within a reasonable time and is subject to technical examination.</p>
    <p>For personally configured SaaS installations with license keys that have been used, downloaded or transferred to third parties, returns may not be accepted due to the nature of the product; This is also stated on the product page.</p>
    <p>For approved returns, the amount is transferred to the Buyer through the channel where the payment was made and within 14 (fourteen) days at the latest.</p>
</div>

<div class="legal__sect" id="m7">
    <h2>7. Dispute and Competent Authorities</h2>
    <p>In disputes arising from this Agreement, Consumer Arbitration Committees and Consumer Courts in the Buyer&#x27;s place of residence are authorized within the monetary limits. The parties may consider pre-litigation conciliation and mediation.</p>
    <p>Your complaints and requests<strong>info@vexaris.net</strong>You can submit a support request at or via the support request on the Site.</p>
</div>

<p class="legal__updated">This Distance Selling Agreement comes into force upon order confirmation. The current version is always published on the Site.</p>
HTML;

    return $d;
}

function legal_get(string $slug): array {
    $def   = legal_defaults();
    $title = legal_titles()[$slug] ?? '';
    $base  = $def[$slug] ?? '';
    if (!$base) return ['title' => $title, 'content' => '', 'updated_at' => null];

    $pdo = db();
    if ($pdo) {
        try {
            $st = $pdo->prepare("SELECT title, content, updated_at FROM legal_pages WHERE slug = ? LIMIT 1");
            $st->execute([$slug]);
            $row = $st->fetch();
            if ($row && trim((string)$row['content']) !== '') {
                return [
                    'title'      => (string)$row['title'],
                    'content'    => (string)$row['content'],
                    'updated_at' => $row['updated_at'],
                ];
            }
        } catch (Exception $e) {}
    }
    return ['title' => $title, 'content' => $base, 'updated_at' => null];
}

function legal_save(string $slug, string $content): void {
    if (!legal_slug_valid($slug)) return;
    $title  = legal_titles()[$slug] ?? $slug;
    $content = trim($content);
    $pdo = db();
    if (!$pdo) return;
    try {
        if ($content === '') {
            $pdo->prepare("DELETE FROM legal_pages WHERE slug = ?")->execute([$slug]);
            return;
        }
        $pdo->prepare("INSERT INTO legal_pages (slug, title, content) VALUES (?,?,?) ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content)")
            ->execute([$slug, $title, $content]);
    } catch (Exception $e) {
        error_log('legal_save hatası: ' . $e->getMessage());
    }
}

function legal_render(string $slug): string {
    $p = legal_get($slug);
    return $p['content'];
}

function legal_date_tr(?string $dt): ?string {
    if (!$dt) return null;
    $ts = strtotime($dt);
    if (!$ts) return null;
    return date('j F Y', $ts);
}
