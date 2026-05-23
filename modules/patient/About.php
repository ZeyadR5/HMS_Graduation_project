<?php
session_start();
define('HMS_SKIP_AUTO_CONNECT', true);
require_once __DIR__ . '/../../includes/config.php';
$connect = hms_db_connect();
if ($connect->connect_error) {
    die("Connection failed: " . $connect->connect_error);
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About</title>
    <link rel="stylesheet" href="./about.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="icon" href="/hms/assets/images/echol.png">

    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>

<body>
    <div class="bg-white">
        <div class="relative isolate px-6 pt-14 lg:px-8">
            <div class="absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-top-80"
                aria-hidden="true">
                <div
                    class="relative left-[calc(50%-11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-[#ff80b5] to-[#9089fc] opacity-30 sm:left-[calc(50%-30rem)] sm:w-[72.1875rem]"
                    style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)">
                </div>
            </div>

           <!-- Ø¨Ø¹Ø¯ Ø¥ØºÙ„Ø§Ù‚ Ø§Ù„Ù€ header -->
<main>
    <!-- Ù…Ø³Ø§ÙØ© Ø¨ÙŠÙ† Ø§Ù„Ù†Ø§Ù Ø¨Ø§Ø± ÙˆØ§Ù„ØµÙˆØ±Ø© -->
    <div class="mt-20"></div> <!-- ÙŠØ¹Ù†ÙŠ 5rem Ù…Ù† ÙÙˆÙ‚ØŒ ØªÙ‚Ø¯Ø±ÙŠ ØªØ²ÙˆØ¯ÙŠ Ø£Ùˆ ØªÙ‚Ù„Ù„ÙŠ Ø­Ø³Ø¨ Ø§Ù„Ø­Ø§Ø¬Ø© -->

    
    
<section class="relative w-full h-[500px]">

  <!-- Ø§Ù„ØµÙˆØ±Ø© -->
  <img src="/hms/assets/images/doctor.jpg"
       class="absolute inset-0 w-full h-full object-cover"
       alt="Doctor">

  <!-- Ø·Ø¨Ù‚Ø© ØºØ§Ù…Ù‚Ø© Ø®ÙÙŠÙØ© -->
  <div class="absolute inset-0 bg-black/40"></div>

  <!-- Ø§Ù„Ù…Ø­ØªÙˆÙ‰ -->
  <div class="relative z-10 flex flex-col items-center justify-center h-full text-center text-white px-4">

    <h2 class="text-4xl md:text-5xl font-bold mb-4">
     WELCOME TO <span class="font-extrabold">ECHO</span>
    </h2>

    <p class="mb-8 text-lg md:text-xl max-w-2xl">


We provide reliable and fast medical services to assist patients around the clock.
    </p>

    <!-- Search -->
    <form class="flex w-full max-w-2xl bg-white rounded-xl overflow-hidden shadow-lg">
      
      <button
        type="submit"
        class="bg-blue-700 hover:bg-blue-900 text-white px-8 py-3 font-semibold transition">
        search
      </button>

      <input
        type="search"
        placeholder="search..."
        class="flex-grow px-6 py-3 text-gray-800 focus:outline-none text-right"
      />

    </form>

  </div>

</section>
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    <!-- About Us Section 
    <section class="relative w-full h-96">
        <img src="/hms/assets/images/doctor.jpg" alt="About Us" class="w-full h-full object-cover">

        <div class="absolute inset-0 bg-black bg-opacity-30 flex flex-col items-center justify-center px-4">-->
           <!-- <h2 class="text-4xl font-bold text-white text-center">About Us</h2>
            <p class="text-lg text-white mt-4 text-center max-w-2xl">
                Welcome to ECHO, where your health and well-being are our top priorities. Our team of experienced healthcare professionals ensures you get the best care.
            </p>-->
        </div>
    </section>
</main>





               
                        <header class="absolute inset-x-0 top-0 z-50">
                <nav class="bg-blue-700">
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div class="flex h-16 items-center justify-between">
                            <div class="flex items-center">
                                <a href="/index.php" class="flex-shrink-0">
                                    <span class="sr-only">ECHO</span>
                                    <img class="h-16 w-16" src="/assets/images/l-gh.png" alt="ECHO">
                                </a>
                            </div>
                            <div class="flex items-center">
                                <a href="/index.php" class="rounded-md bg-white px-5 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-gray-100">
                                    Log in
                                </a>
                            </div>
                        </div>
                    </div>
                </nav>
            </header>

            <main>




            <section class="bg-white text-center py-12 px-4">

  <h2 class="text-4xl font-bold text-blue-700 mb-4">
    ECHO Medical Group
  </h2>

  <p class="text-gray-600 text-lg mb-6">
    In Echo We Care About You
  </p>

  <button class="bg-blue-700 hover:bg-blue-900 text-white px-6 py-2 rounded-lg transition">
    Learn More
  </button>

</section>































               <!--
                <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                    <div class="relative isolate px-6 lg:px-8 -translate-y-12">
                        <div class="mx-auto max-w-2xl sm:py-48 lg:py-56">
                            <div class="text-center">
                               <!-- <img class="relative left-56 mx-4 bottom-12 h-48 w-48" src="/hms/assets/images/black echo.png">-
                                <h1 class="text-8xl font-bold tracking-tight text-gray-900 sm:text-6xl">ECHO Medical
                                    Group</h1>
                                <p class="mt-6 text-lg font-bold leading-10 text-gray-900"> In Echo We Care About You
                                </p>
                                <form action="./done-patint/index.php" method="post"
                                    class="mt-10 flex items-center justify-center gap-x-6">
                                    <button name="login1"
                                        class="rounded-md bg-blue-700 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                        Get started
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>-->

                   <!-- <div class="h-72 w-full overflow-hidden rounded-lg relative bottom-20">
                        <h1 class="font-bold tracking-tight text-gray-900 sm:text-3xl relative top-32 left-5 ">About
                            Us</h1>
                        <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-60 ">
                            <img src="/hms/assets/images/breadcrumb-image-1.jpg"
                                class="h-full w-full object-cover object-center lg:h-full lg:w-full">-->
                        </div>
                    </div> 




                <!--ed-->
                <div class="relative overflow-hidden bg-white">
            <div class="pb-80 pt-16 sm:pb-40 sm:pt-24 lg:pb-48 lg:pt-40">
              <div class="relative mx-auto max-w-7xl px-4 sm:static sm:px-6 lg:px-8">
                <div class="sm:max-w-lg">
                  <h1 class="text-4xl font-bold tracking-tight text-blue-700 sm:text-6xl">About Us</h1>
                  <p class="mt-4 text-xl text-gray-900">Welcome to ECHO , where your health and well-being are our top priorities. Our team of experienced healthcare professionals and medical writers work tirelessly to ensure that the content we provide is accurate, easy to understand, and relevant to your needs </p>
                </div>
                <div>
                  <div class="mt-10">

                    <div aria-hidden="true" class="pointer-events-none lg:absolute lg:inset-y-0 lg:mx-auto lg:w-full lg:max-w-7xl">
                      <div class="absolute transform sm:left-1/2 sm:top-0 sm:translate-x-8 lg:left-1/2 lg:top-1/2 lg:-translate-y-1/2 lg:translate-x-8">
                        <div class="flex items-center space-x-6 lg:space-x-8">
                          <div class="grid flex-shrink-0 grid-cols-1 gap-y-6 lg:gap-y-8">
                            <div class="h-64 w-44 overflow-hidden rounded-lg sm:opacity-0 lg:opacity-100">
                              <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-48 ">
                                <img src="/hms/assets/images/about-horizontal-img.jpg" class="h-full">
                              </div>
                            </div>
                            <div class="h-64 w-44 overflow-hidden rounded-lg">
                              <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-48 ">
                                <img src="/hms/assets/images/docs.jpeg" class="h-full">
                              </div>
                            </div>
                          </div>
                          <div class="grid flex-shrink-0 grid-cols-1 gap-y-6 lg:gap-y-8">
                            <div class="h-64 w-44 overflow-hidden rounded-lg">
                              <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-48 ">
                                <img src="/hms/assets/images/dental.jpg" class="h-full">
                              </div>
                            </div>
                            <div class="h-64 w-44 overflow-hidden rounded-lg">
                              <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-44 ">
                                <img src="/hms/assets/images/dc.jpg" class="h-full">
                              </div>
                            </div>
                            
                          </div>
                          <div class="grid flex-shrink-0 grid-cols-1 gap-y-6 lg:gap-y-8">
                            <div class="h-64 w-44 overflow-hidden rounded-lg">
                              <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-48 ">
                                <img src="/hms/assets/images/dov.jpeg" class="h-full">
                              </div>
                            </div>
                            <div class="h-64 w-44 overflow-hidden rounded-lg">
                              <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-48 ">
                                <img src="/hms/assets/images/care.jpeg" class="h-full">
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                <a href="./done-patint/index.php" class="inline-block rounded-md border border-transparent bg-blue-600 px-8 py-3 text-center font-medium text-white hover:bg-indigo-700">Book
                      now</a>
                  </div>
                </div>
              </div>
            </div>
          </div>   

          <!-- Ù‚Ø³Ù… PROJECT ABSTRACT -->
<div class="bg-white py-12 px-4 lg:px-16">
  <div class="mx-auto max-w-7xl lg:grid lg:grid-cols-2 lg:gap-8">

    <!-- Ø§Ù„Ù†Øµ Ø¹Ù„Ù‰ Ø§Ù„Ø´Ù…Ø§Ù„ -->
    <div class="lg:order-1">
      <h2 class="text-3xl font-bold text-blue-700 sm:text-4xl">PROJECT ABSTRACT</h2>
      <p class="mt-4 text-gray-900">
        ECHO Medical Website is a comprehensive digital platform developed to streamline healthcare services by enabling patients to easily book appointments, access their medical records, and communicate with healthcare professionals efficiently. The system aims to reduce waiting times and improve the overall patient experience through a user-friendly and responsive design.
      </p>
      <p class="mt-2 text-gray-900">
        The website was built using modern web technologies: HTML for structure, Tailwind CSS for responsive and attractive styling, JavaScript for interactive front-end functionality, and MySQL for secure database management of patient data, appointments, and medical records.
      </p>
      <p class="mt-2 text-gray-900">
        The system workflow follows a logical sequence:<br>
        Patient Registration â†’ Secure Login â†’ Dashboard Overview â†’ Book Appointment â†’ View Medical Reports â†’ Admin Management Panel
      </p>
      <p class="mt-2 text-gray-900">
        Key features include: user authentication, appointment scheduling with date/time validation, admin dashboard for managing doctors and appointments, and a responsive design compatible with all devices. The project successfully demonstrates how technology can enhance traditional healthcare processes by providing an accessible and efficient online solution.
      </p>
    </div>
























    <!-- Ø§Ù„ØµÙˆØ± Ø¹Ù„Ù‰ Ø§Ù„ÙŠÙ…ÙŠÙ† -->
    <div class="grid grid-cols-2 gap-4 lg:order-2">
      <!-- ØµÙ 1 -->
      <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200">
        <img src="/hms/assets/images/capture.PNG sooo.PNG" alt="Home Page" class="w-full h-full object-cover">
      </div>
      <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200">
        <img src="/hms/assets/images/capture.PNG kooo.PNG" alt="Login Page" class="w-full h-full object-cover">
      </div>
      
      <!-- ØµÙ 2 -->
      <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200">
        <img src="/hms/assets/images/capture.PNGpki.PNg" alt="Booking Page" class="w-full h-full object-cover">
      </div>

      <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200">
        <img src="/hms/assets/images/capture.PNGdhks.PNg" alt="System Flowchart" class="w-full h-full object-cover">
      </div>



       <!-- ØµÙ 3 -->
      <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200">
        <img src="/hms/assets/images/capture.PNGsa.PNg" alt="Booking Page" class="w-full h-full object-cover">
      </div>
      
      <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200">
        <img src="/hms/assets/images/capture.PNGlo.PNg" alt="System Flowchart" class="w-full h-full object-cover">
      </div>
    </div>
  </div>
</div>




          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          <div class="bg-white">
            <div class="mx-auto grid max-w-2xl grid-cols-1 items-center gap-x-8 gap-y-16 px-4 py-24 sm:px-6 sm:py-32 lg:max-w-7xl lg:grid-cols-2 lg:px-8">
              <div>


                <h2 class="text-3xl font-bold tracking-tight text-blue-700 sm:text-4xl">Explore Some Of Our Main Service</h2>
                <p class="mt-4 text-gray-900">If you are looking for information on specific conditions, tips for maintaining a healthy lifestyle, or the latest in medical research, you can trust us to be your go-to resource. At ECHO , we believe that everyone deserves access to high-quality healthcare information, and we are committed to empowering you on your journey to better health..</p>

                <dl class="mt-16 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 sm:gap-y-16 lg:gap-x-8">
                  <div class="border-t border-gray-200 pt-4">
                    <dt class="font-medium text-gray-900">
                      Cardiology</dt>
                    <dd class="mt-2 text-sm text-gray-900">Cardiology focuses on diagnosing and treating heart ailments like arrhythmias and congenital defects. It utilizes tools like ECGs to assess heart health and guide treatment plans.</dd>
                  </div>
                  <div class="border-t border-gray-200 pt-4">
                    <dt class="font-medium text-gray-900">Oncologist</dt>
                    <dd class="mt-2 text-sm text-gray-900">An oncologist is a medical doctor specializing in cancer care. They guide patients through diagnosis, treatment, and post-treatment care, working with a team to create personalized plans. In essence, oncologists are cancer specialists who oversee all aspects of a patient's care.</dd>
                  </div>
                  <div class="border-t border-gray-200 pt-4">
                    <dt class="font-medium text-gray-900">Plastic surgery</dt>
                    <dd class="mt-2 text-sm text-gray-900">Plastic surgery tackles both cosmetic and medical concerns. It uses a blend of artistry and medical knowledge to reconstruct, repair, or improve physical appearance and function..</dd>
                  </div>
                  <div class="border-t border-gray-200 pt-4">
                    <dt class="font-medium text-gray-900">
                      Eye Care</dt>
                    <dd class="mt-2 text-sm text-gray-900">Your trusted partner in eye care! We offer comprehensive resources & expert advice to keep your vision healthy.</dd>
                  </div>
                  <div class="border-t border-gray-200 pt-4">
                    <dt class="font-medium text-gray-900">Neurology</dt>
                    <dd class="mt-2 text-sm text-gray-900">Neurology is the medical field for nervous system disorders like epilepsy and migraines. Neurologists use expertise and research to improve patients' lives.</dd>
                  </div>
                  <div class="border-t border-gray-200 pt-4">
                    <dt class="font-medium text-gray-900">Pediatric specialist</dt>
                    <dd class="mt-2 text-sm text-gray-900">
                      Board-certified pediatricians at your practice offer compassionate care for infants, children, and adolescents. They address everything from routine check-ups to complex conditions, ensuring your child's optimal health in a supportive environment.</dd>
                  </div>
                </dl>
              </div>
              <div class="grid grid-cols-2 grid-rows-2 gap-4 sm:gap-6 lg:gap-8 ">
                <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-48 ">
                  <img src="/hms/assets/images/heartph.jpg" class="w-full h-full">
                </div>
                <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-48 ">
                  <img src="/hms/assets/images/awram.jpg" class="w-full h-full">
                </div>
                <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-48 ">
                  <img src="/hms/assets/images/tagmel.jpg" class="w-full h-full">
                </div>
                <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-48 ">
                  <img src="/hms/assets/images/Eyecare.jpg" class="w-full h-full">
                </div>
                <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-48 ">
                  <img src="/hms/assets/images/brain.jpeg" class="w-full h-full">
                </div>
                <div class="aspect-h-1 aspect-w-1 overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-48 ">
                  <img src="/hms/assets/images/baby.jpg" class="w-full h-full">
                </div>
              </div>
            </div>
          </div>

<!--
           <div class="bg-white">
            <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6 sm:py-24 lg:max-w-7xl lg:px-8">
              <h2 class="text-2xl font-bold tracking-tight text-gray-900">Our Client Happy Say About Us</h2>

              <div class="mt-6 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4 xl:gap-x-8">
                <div class="group relative">
                  <div class="aspect-h-1 aspect-w-1 w-full overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-80">

                  </div>
                </div>
                <div class="group relative">
                  <div class="aspect-h-1 aspect-w-1 w-full overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-80">

                  </div>
                </div>
                <div class="group relative">
                  <div class="aspect-h-1 aspect-w-1 w-full overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-80">

                  </div>
                </div>
                <div class="group relative">
                  <div class="aspect-h-1 aspect-w-1 w-full overflow-hidden rounded-md bg-gray-200 lg:aspect-none group-hover:opacity-75 lg:h-80">

                  </div>
                </div>
              </div>
            </div>
          </div> -->
          <!--
          <div class="w-full h-px bg-gray-800 mb-2"></div>

          <div class="bg-white py-24 sm:py-32">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
              <h2 class="text-center text-lg font-semibold leading-8 text-gray-900">Trusted by the worldâ€™s most innovative teams
              </h2>
              <div class="mx-auto mt-10 grid max-w-lg grid-cols-4 items-center gap-x-8 gap-y-10 sm:max-w-xl sm:grid-cols-6 sm:gap-x-10 lg:mx-0 lg:max-w-none lg:grid-cols-5">
                <img class="col-span-2 max-h-12 w-full object-contain lg:col-span-1" src="/hms/assets/images/1.png" width="164" height="48">
                <img class="col-span-2 max-h-12 w-full object-contain lg:col-span-1" src="/hms/assets/images/2.png" width="164" height="48">
                <img class="col-span-2 max-h-12 w-full object-contain lg:col-span-1" src="/hms/assets/images/3.png" width="164" height="48">
                <img class="col-span-2 max-h-12 w-full object-contain sm:col-start-2 lg:col-span-1" src="/hms/assets/images/4.png" width="164" height="48">
                <img class="col-span-2 col-start-2 max-h-12 w-full object-contain sm:col-start-auto lg:col-span-1" src="/hms/assets/images/5.png" width="164" height="48">
              </div>
            </div>
          </div>



        </div>-->

        <div class="bg-white pt-6 pb-24 sm:pb-32"> <!-- Ù‚Ù„Ù„Ù†Ø§ Ø§Ù„Ù…Ø³Ø§ÙØ© ÙÙˆÙ‚ -->
  
  <!-- Ø§Ù„Ø®Ø· ØªØ­Øª Ø§Ù„ÙƒÙ„Ø§Ù… Ø§Ù„Ù„ÙŠ ÙÙˆÙ‚ Ù…Ø¨Ø§Ø´Ø±Ø© -->
  <div class="w-full h-px bg-gray-800 mb-2"></div>
  
  <div class="mx-auto max-w-7xl px-6 lg:px-8">
    <h2 class="text-center text-lg font-semibold leading-8 text-gray-900">
      Trusted by the worldâ€™s most innovative teams
    </h2>

    <div class="mx-auto mt-10 grid max-w-lg grid-cols-4 items-center gap-x-8 gap-y-10 sm:max-w-xl sm:grid-cols-6 sm:gap-x-10 lg:mx-0 lg:max-w-none lg:grid-cols-5">
      <img class="col-span-2 max-h-12 w-full object-contain lg:col-span-1" src="/hms/assets/images/1.png" width="164" height="48">
      <img class="col-span-2 max-h-12 w-full object-contain lg:col-span-1" src="/hms/assets/images/2.png" width="164" height="48">
      <img class="col-span-2 max-h-12 w-full object-contain lg:col-span-1" src="/hms/assets/images/3.png" width="164" height="48">
      <img class="col-span-2 max-h-12 w-full object-contain sm:col-start-2 lg:col-span-1" src="/hms/assets/images/4.png" width="164" height="48">
      <img class="col-span-2 col-start-2 max-h-12 w-full object-contain sm:col-start-auto lg:col-span-1" src="/hms/assets/images/5.png" width="164" height="48">
    </div>
  </div>
</div>























        
      </main>

    <script src="/assets/js/responsive-nav.js" defer></script>
</body>

</html>























                    <!-- Ø¨Ø§Ù‚ÙŠ Ù…Ø­ØªÙˆÙ‰ Ø§Ù„ØµÙØ­Ø© ÙŠØ¨Ù‚Ù‰ Ø¨Ù†ÙØ³ Ø§Ù„Ø·Ø±ÙŠÙ‚Ø©ØŒ ÙƒÙ„ div Ùˆ img Ùˆ p -->
                    <!-- ... -->
                </div>
            </main>
        </div>
    </div>
</body>

</html>
