<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سياسة الخصوصية</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-300">

    <!-- Header -->
    <header class="bg-gray-900 w-full shadow-md z-10 text-white">
        <nav class="container mx-auto flex flex-wrap justify-between items-center p-4 space-y-2 sm:space-y-0">
            <h1 class="text-2xl font-bold">سياسة الخصوصية</h1>
            <button id="dark-mode-toggle" class="ml-3 px-4 py-2 rounded bg-gray-800 dark:bg-gray-600 hover:bg-gray-700 dark:hover:bg-gray-500 transition">
                تبديل الوضع الليلي
            </button>
        </nav>
    </header>

    <!-- Privacy Policy Section -->
    <section class="container mx-auto mt-8 p-6 bg-white dark:bg-gray-800 shadow-md rounded-lg">
        <h2 class="text-2xl font-bold mb-4">سياسة الخصوصية</h2>
        <p class="text-gray-700 dark:text-gray-300 mb-4">
            نحن ملتزمون بحماية خصوصيتك وضمان أمان بياناتك الشخصية. توضح هذه السياسة كيفية جمع واستخدام وحماية البيانات الشخصية عند استخدامك لمنصتنا.
        </p>

        <h3 class="text-xl font-bold mt-6 mb-2">جمع البيانات</h3>
        <p class="text-gray-700 dark:text-gray-300">
            قد نقوم بجمع المعلومات التالية:
        </p>
        <ul class="list-disc pl-6 text-gray-700 dark:text-gray-300 space-y-2">
            <li>الاسم الكامل وبيانات الاتصال.</li>
            <li>معلومات الحساب (مثل اسم المستخدم والبريد الإلكتروني).</li>
            <li>بيانات تسجيل الدخول والنشاطات على المنصة.</li>
        </ul>

        <h3 class="text-xl font-bold mt-6 mb-2">استخدام البيانات</h3>
        <p class="text-gray-700 dark:text-gray-300">
            نستخدم البيانات الشخصية للأغراض التالية:
        </p>
        <ul class="list-disc pl-6 text-gray-700 dark:text-gray-300 space-y-2">
            <li>تقديم الخدمات التعليمية والمحتوى المناسب.</li>
            <li>تحسين تجربة المستخدم.</li>
            <li>التواصل معك بشأن التحديثات أو الاستفسارات.</li>
        </ul>

        <h3 class="text-xl font-bold mt-6 mb-2">مشاركة البيانات</h3>
        <p class="text-gray-700 dark:text-gray-300">
            لن نشارك بياناتك مع أي طرف ثالث، باستثناء:
        </p>
        <ul class="list-disc pl-6 text-gray-700 dark:text-gray-300 space-y-2">
            <li>إذا تطلب الأمر قانونيًا أو بموجب طلب حكومي.</li>
            <li>عند الضرورة لتقديم الخدمة (مثل خدمات الدفع).</li>
        </ul>

        <h3 class="text-xl font-bold mt-6 mb-2">أمان البيانات</h3>
        <p class="text-gray-700 dark:text-gray-300">
            نستخدم تقنيات أمان حديثة لحماية بياناتك من الوصول غير المصرح به أو التعديل أو الحذف.
        </p>

        <h3 class="text-xl font-bold mt-6 mb-2">حقوق المستخدم</h3>
        <p class="text-gray-700 dark:text-gray-300">
            يحق لك:
        </p>
        <ul class="list-disc pl-6 text-gray-700 dark:text-gray-300 space-y-2">
            <li>الوصول إلى بياناتك الشخصية أو طلب تعديلها.</li>
            <li>طلب حذف بياناتك الشخصية في أي وقت.</li>
        </ul>

        <h3 class="text-xl font-bold mt-6 mb-2">تحديث السياسة</h3>
        <p class="text-gray-700 dark:text-gray-300">
            قد نقوم بتحديث هذه السياسة من وقت لآخر. نوصي بمراجعتها دوريًا لضمان معرفتك بأحدث التعديلات.
        </p>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-6 text-center text-sm sm:text-base mt-16">
        <p>&copy; 2024 جامعة الزقازيق. جميع الحقوق محفوظة.</p>
    </footer>

    <!-- JavaScript -->
    <script>
        // Toggle Dark Mode
        document.getElementById('dark-mode-toggle').addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
        });
    </script>

</body>

</html>
