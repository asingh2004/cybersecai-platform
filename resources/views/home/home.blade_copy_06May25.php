@extends('template')

@push('css')
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
	<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
	body,h1,h2,h3,h4,h5,h6 {font-family: "Raleway", sans-serif}

	body, html {
  	height: 100%;
  	line-height: 1.8;
	}

	/* Full height image header */
	.bgimg-1 {
  	background-position: center;
  	background-size: cover;
  	background-image: url("{{ asset('public/front/images/home/hero_image_1.png') }}");
  	min-height: 100%;
	}

	.w3-bar .w3-button {
  	padding: 16px;
	}
  
  /* For Roadmap */
  	* {
  box-sizing: border-box;
}

body {
  background-color: #474e5d;
  font-family: Helvetica, sans-serif;
}

/* The actual timeline (the vertical ruler) */
.timeline {
  position: relative;
  max-width: 1200px;
  margin: 0 auto;
}

/* The actual timeline (the vertical ruler) */
.timeline::after {
  content: '';
  position: absolute;
  width: 6px;
  background-color: white;
  top: 0;
  bottom: 0;
  left: 50%;
  margin-left: -3px;
}

/* Container around content */
.container {
  padding: 10px 40px;
  position: relative;
  background-color: inherit;
  width: 50%;
}

/* The circles on the timeline */
.container::after {
  content: '';
  position: absolute;
  width: 25px;
  height: 25px;
  right: -17px;
  background-color: white;
  border: 4px solid #FF9F55;
  top: 15px;
  border-radius: 50%;
  z-index: 1;
}

/* Place the container to the left */
.left {
  left: 0;
}

/* Place the container to the right */
.right {
  left: 50%;
}

/* Add arrows to the left container (pointing right) */
.left::before {
  content: " ";
  height: 0;
  position: absolute;
  top: 22px;
  width: 0;
  z-index: 1;
  right: 30px;
  border: medium solid white;
  border-width: 10px 0 10px 10px;
  border-color: transparent transparent transparent white;
}

/* Add arrows to the right container (pointing left) */
.right::before {
  content: " ";
  height: 0;
  position: absolute;
  top: 22px;
  width: 0;
  z-index: 1;
  left: 30px;
  border: medium solid white;
  border-width: 10px 10px 10px 0;
  border-color: transparent white transparent transparent;
}

/* Fix the circle for containers on the right side */
.right::after {
  left: -16px;
}

/* The actual content */
.content {
  padding: 20px 30px;
  background-color: white;
  position: relative;
  border-radius: 6px;
}

/* Media queries - Responsive timeline on screens less than 600px wide */
@media screen and (max-width: 600px) {
  /* Place the timelime to the left */
  .timeline::after {
  left: 31px;
  }
  
  /* Full-width containers */
  .container {
  width: 100%;
  padding-left: 70px;
  padding-right: 25px;
  }
  
  /* Make sure that all arrows are pointing leftwards */
  .container::before {
  left: 60px;
  border: medium solid white;
  border-width: 10px 10px 10px 0;
  border-color: transparent white transparent transparent;
  }

  /* Make sure all circles are at the same spot */
  .left::after, .right::after {
  left: 15px;
  }
  
  /* Make all right containers behave like the left ones */
  .right {
  left: 0%;
  }
	}
</style>
@endpush

@section('main')


<!-- Header with full-height image -->
<header class="bgimg-1 w3-display-container w3-grayscale-min" id="home">
  <div class="w3-display-left w3-text-white" style="padding:48px">
    
    <span class="w3-jumbo w3-hide-small">Automate Unified File Data Security and Compliance</span><br>
    <span class="w3-xxlarge w3-hide-large w3-hide-medium">Automate Unified File Data Security and Compliance</span><br>
    <span class="w3-large">We unlock a consolidated, automated, future-proof way to secure files everywhere—so enterprises can move faster, stay compliant, and trust their most valuable asset: data.</span>
    <p><a href="#about" class="w3-button w3-white w3-padding-large w3-large w3-margin-top w3-opacity w3-hover-opacity-off">Learn more and start today</a></p>
  </div> 
  <div class="w3-display-bottomleft w3-text-grey w3-large" style="padding:24px 48px">
    <i class="fa fa-facebook-official w3-hover-opacity"></i>
    <i class="fa fa-instagram w3-hover-opacity"></i>
    <i class="fa fa-snapchat w3-hover-opacity"></i>
    <i class="fa fa-pinterest-p w3-hover-opacity"></i>
    <i class="fa fa-twitter w3-hover-opacity"></i>
    <i class="fa fa-linkedin w3-hover-opacity"></i>
  </div>
</header>

<!-- About Section -->
<div class="w3-container" style="padding:128px 16px" id="about">
  <h3 class="w3-center">WHAT WILL YOU CREATE TODAY?</h3>
  <p class="w3-center w3-large">Key capabilities</p>
  <div class="w3-row-padding w3-center" style="margin-top:64px">
    <div class="w3-quarter">
      <i class="fa fa-desktop w3-margin-bottom w3-jumbo w3-center"></i>
      <p class="w3-large">Sophisticated Assistant</p>
      <p>Build sophisticated AI assistant that you can share with your team to perform specific tasks. Users won't be able to change configuration of your Assistant, allowing you to enforce guardrails.</p>
    </div>
    <div class="w3-quarter">
      <i class="fa fa-heart w3-margin-bottom w3-jumbo"></i>
      <p class="w3-large">Conversational Bot</p>
      <p>Template to create chat bot for your specific needs. It supports text and image inputs, and can output text content.</p>
    </div>
    <div class="w3-quarter">
      <i class="fa fa-diamond w3-margin-bottom w3-jumbo"></i>
      <p class="w3-large">Large Scale PDF Summariser</p>
      <p>This template allows you to upload large PDFs and the AI summarises it for you. Unlike many assistants, this assistant is capable of summarising very large PDFs.</p>
    </div>
    <div class="w3-quarter">
      <i class="fa fa-cog w3-margin-bottom w3-jumbo"></i>
      <p class="w3-large">Blog or Web Page Summariser</p>
      <p>Template to create a bot to summarise a number of blogs. All you have to do is enter the URLs!</p>
    </div>
  </div>
</div>

<!-- Promo Section - "We know design" -->
<div class="w3-container w3-light-grey" style="padding:128px 16px">
  <div class="w3-row-padding">
    <div class="w3-col m6">
      <h1>myopenai.io</h1>
      <p>Launching Soon</p>
      <p><a href="#work" class="w3-button w3-black"><i class="fa fa-th"> </i> View Timeline</a></p>
    </div>
    <!--<div class="w3-col m6">-->
     <!-- <img class="w3-image w3-round-large" src="{{ asset('public/front/images/home/hero_image_2.jpg') }}" alt="Buildings" width="700" height="394">
    </div>-->
   
    <!--</div>-->
  </div>
</div>

<!-- Team Section -->
<div class="w3-container" style="padding:128px 16px" id="team">
  <h3 class="w3-center">Choose a Persona</h3>
  <p class="w3-center w3-large">And let the magical AI bots jumstart your journey!</p>
  <div class="w3-row-padding w3-grayscale" style="margin-top:64px">
    <div class="w3-col l3 m6 w3-margin-bottom">
      <div class="w3-card">
        <img src="{{ asset('public/front/images/home/team2.jpg') }}" alt="John" style="width:100%">
        <div class="w3-container">
          <h3>John Doe</h3>
          <p class="w3-opacity">Board Member</p>
          <p>An AI Assistant can assist a board member by reviewing Board papers by adopting SME persona, providing succinct summaries of papers and 
            financial reports to support informed decision-making, and strategic alignment. You can even get the assistant / bot to provide insights on
          submitted papers in the context of applicable legislation/ Act, strategy and organisation's policies. Accelerate identification of 
            business critical risks based
          on the industry the company is operating in, including mitigations.</p>
          <p><button class="w3-button w3-light-grey w3-block"><a class="nav-link" href="{{ url('login') }}" 
                                                                 aria-label="login">Get me started</a></button></p>
        </div>
      </div>
    </div>
    <div class="w3-col l3 m6 w3-margin-bottom">
      <div class="w3-card">
        <img src="{{ asset('public/front/images/home/team3.jpg') }}" alt="Mike" style="width:100%">
        <div class="w3-container">
          <h3>Mike Ross</h3>
          <p class="w3-opacity">Academic & Researcher</p>
          <p>A custom AI Assistant can significantly benefit an academic and a researcher by automating literature reviews, summarising extensive research papers, and suggesting relevant studies. Additionally, it can assist with grant writing by proposing structures and highlighting key points, ensuring compliance with submission guidelines.
          It can also assist in creating course rubric, assessment criteria, course schedule and more. You are share the AI assistant with students
          with appropriate guardrails in place.</p>
          <p><button class="w3-button w3-light-grey w3-block"><a class="nav-link" href="{{ url('login') }}" 
                                                                 aria-label="login">Get me started</a></button></p>
        </div>
      </div>
    </div>
    <div class="w3-col l3 m6 w3-margin-bottom">
      <div class="w3-card">
        <img src="{{ asset('public/front/images/home/team4.jpg') }}" alt="Dan" style="width:100%">
        <div class="w3-container">
          <h3>Dan Star</h3>
          <p class="w3-opacity">Cyber Security Expert & Consultant</p>
          <p>An AI Assistant can greatly aid a cybersecurity expert and consultant by automating the analysis of Security Operations 
            Center (SOC) reports, identifying patterns, and flagging potential threats for further investigation. 
            It can streamline the creation of cybersecurity strategies by aggregating and analysing regulatory guidelines, industry standards, 
            and best practices. Additionally, it can enhance GRC efforts by generating audit-ready reports, and recommending proactive measures to mitigate risks.</p>
          <p><button class="w3-button w3-light-grey w3-block"><a class="nav-link" href="{{ url('login') }}" 
                                                                 aria-label="login">Get me started</a></button></p>
        </div>
      </div>
    </div>
    <div class="w3-col l3 m6 w3-margin-bottom">
      <div class="w3-card">
        <img src="{{ asset('public/front/images/home/team1.jpg') }}" alt="Jane" style="width:100%">
        <div class="w3-container">
          <h3>Anja Doe</h3>
          <p class="w3-opacity">Student</p>
          <p>An AI Assistant can support a university and school student aiming to improve performance and grades by offering personalised study 
            plans based on upcoming deadlines and exams. It can provide instant explanations and tutoring on 
            challenging subjects, and generate practice questions and quizzes for better memory retention. Additionally, it can assist in 
            organising notes, summarising teaching materials, and managing time efficiently. It can cite sources for content and source
   		cloud.</p>
          <p><button class="w3-button w3-light-grey w3-block"><a class="nav-link" href="{{ url('login') }}" 
                                                                 aria-label="login">Get me started</a></button></p>
        </div>
      </div>
    </div>
    
  </div>
</div>

<!-- Promo Section "Statistics" -->
<div class="w3-container w3-row w3-center w3-dark-grey w3-padding-64">

  <div class="w3-quarter">
    <span class="w3-xxlarge">5+</span>
    <br>Supported Personas
  </div>
  <div class="w3-quarter">
    <span class="w3-xxlarge">12+</span>
    <br>Custom AI Bots Templates
  </div>
  <div class="w3-quarter">
    <span class="w3-xxlarge">6+</span>
    <br>Create Bots Chain
  </div>
  <div class="w3-quarter">
    <span class="w3-xxlarge">5+</span>
    <br>Cloud Data Store - Data Protection
  </div>
</div>

<!-- Work Section -->
<div class="w3-container" style="padding:128px 16px" id="work">
  <h3 class="w3-center">LAUNCH TIMELINE</h3>
  <p class="w3-center w3-large">Our Roadmap</p>
  
  
    <div class=" w3-row w3-dark-grey">
   <div class="timeline">
  <div class="container left">
    <div class="content">
      <h2>December 2024: Beta will be launched.</h2>
    </div>
  </div>

  <div class="container left">
    <div class="content">
      <h2>February 2025: Soft Launch. </h2>
    </div>
  </div>

  <div class="container left">
    <div class="content">
      <h2>June 2025: Global Launch.</h2>

    </div>
  </div>

</div>
      
      
      

  <!--<div class="w3-row-padding" style="margin-top:64px">
    <div class="w3-col l3 m6">
      <img src="/w3images/tech_mic.jpg" style="width:100%" onclick="onClick(this)" class="w3-hover-opacity" alt="A microphone">
    </div>
    <div class="w3-col l3 m6">
      <img src="/w3images/tech_phone.jpg" style="width:100%" onclick="onClick(this)" class="w3-hover-opacity" alt="A phone">
    </div>
    <div class="w3-col l3 m6">
      <img src="/w3images/tech_drone.jpg" style="width:100%" onclick="onClick(this)" class="w3-hover-opacity" alt="A drone">
    </div>
    <div class="w3-col l3 m6">
      <img src="/w3images/tech_sound.jpg" style="width:100%" onclick="onClick(this)" class="w3-hover-opacity" alt="Soundbox">
    </div>
  </div>

  <div class="w3-row-padding w3-section">
    <div class="w3-col l3 m6">
      <img src="/w3images/tech_tablet.jpg" style="width:100%" onclick="onClick(this)" class="w3-hover-opacity" alt="A tablet">
    </div>
    <div class="w3-col l3 m6">
      <img src="/w3images/tech_camera.jpg" style="width:100%" onclick="onClick(this)" class="w3-hover-opacity" alt="A camera">
    </div>
    <div class="w3-col l3 m6">
      <img src="/w3images/tech_typewriter.jpg" style="width:100%" onclick="onClick(this)" class="w3-hover-opacity" alt="A typewriter">
    </div>
    <div class="w3-col l3 m6">
      <img src="/w3images/tech_tableturner.jpg" style="width:100%" onclick="onClick(this)" class="w3-hover-opacity" alt="A tableturner">
    </div>
  </div>-->
</div>

<!-- Modal for full size images on click-->
<div id="modal01" class="w3-modal w3-black" onclick="this.style.display='none'">
  <span class="w3-button w3-xxlarge w3-black w3-padding-large w3-display-topright" title="Close Modal Image">×</span>
  <div class="w3-modal-content w3-animate-zoom w3-center w3-transparent w3-padding-64">
    <img id="img01" class="w3-image">
    <p id="caption" class="w3-opacity w3-large"></p>
  </div>
</div>

<!-- Skills Section -->
<div class="w3-container w3-light-grey w3-padding-64">
  <div class="w3-row-padding">
    <div class="w3-col m6">
      <h3>Our Commitment.</h3>
      <p>Security and data privacy is of prime concern to us.</p>
      <p>We leverage services that does not train on your data<br>
      And we don't store your instructions or messages.</p>
    </div>
    <div class="w3-col m6">
      <p class="w3-wide"><i class="fa fa-camera w3-margin-right"></i>Data Security - APIs we use do not train our models on your data</p>
      <div class="w3-grey">
        <div class="w3-container w3-dark-grey w3-center" style="width:100%">100%</div>
      </div>
      <p class="w3-wide"><i class="fa fa-desktop w3-margin-right"></i>LLM Model Choice - You can choose to use latest available OpenAI Models</p>
      <div class="w3-grey">
        <div class="w3-container w3-dark-grey w3-center" style="width:100%">100%</div>
      </div>
      <p class="w3-wide"><i class="fa fa-photo w3-margin-right"></i>Your Custom Bit - Ease of sharing with your friends/ colleagues</p>
      <div class="w3-grey">
        <div class="w3-container w3-dark-grey w3-center" style="width:100%">100%</div>
      </div>
    </div>
  </div>
</div>

<!-- Pricing Section -->
<div class="w3-container w3-center w3-dark-grey" style="padding:128px 16px" id="pricing">
  <h3>PRICING</h3>
  <p class="w3-large">COMING SOON.</p>
  <!--<p class="w3-large">Choose a pricing plan that fits your needs.</p>
  <div class="w3-row-padding" style="margin-top:64px">
    <div class="w3-third w3-section">
      <ul class="w3-ul w3-white w3-hover-shadow">
        <li class="w3-black w3-xlarge w3-padding-32">Basic</li>
        <li class="w3-padding-16"><b>10GB</b> Storage</li>
        <li class="w3-padding-16"><b>10</b> Emails</li>
        <li class="w3-padding-16"><b>10</b> Domains</li>
        <li class="w3-padding-16"><b>Endless</b> Support</li>
        <li class="w3-padding-16">
          <h2 class="w3-wide">$ 10</h2>
          <span class="w3-opacity">per month</span>
        </li>
        <li class="w3-light-grey w3-padding-24">
          <button class="w3-button w3-black w3-padding-large">Sign Up</button>
        </li>
      </ul>
    </div>
    <div class="w3-third">
      <ul class="w3-ul w3-white w3-hover-shadow">
        <li class="w3-red w3-xlarge w3-padding-48">Pro</li>
        <li class="w3-padding-16"><b>25GB</b> Storage</li>
        <li class="w3-padding-16"><b>25</b> Emails</li>
        <li class="w3-padding-16"><b>25</b> Domains</li>
        <li class="w3-padding-16"><b>Endless</b> Support</li>
        <li class="w3-padding-16">
          <h2 class="w3-wide">$ 25</h2>
          <span class="w3-opacity">per month</span>
        </li>
        <li class="w3-light-grey w3-padding-24">
          <button class="w3-button w3-black w3-padding-large">Sign Up</button>
        </li>
      </ul>
    </div>
    <div class="w3-third w3-section">
      <ul class="w3-ul w3-white w3-hover-shadow">
        <li class="w3-black w3-xlarge w3-padding-32">Premium</li>
        <li class="w3-padding-16"><b>50GB</b> Storage</li>
        <li class="w3-padding-16"><b>50</b> Emails</li>
        <li class="w3-padding-16"><b>50</b> Domains</li>
        <li class="w3-padding-16"><b>Endless</b> Support</li>
        <li class="w3-padding-16">
          <h2 class="w3-wide">$ 50</h2>
          <span class="w3-opacity">per month</span>
        </li>
        <li class="w3-light-grey w3-padding-24">
          <button class="w3-button w3-black w3-padding-large">Sign Up</button>
        </li>
      </ul>
    </div>
  </div>-->
</div>

<!-- Contact Section -->
<!-- <div class="w3-container w3-light-grey" style="padding:128px 16px" id="contact">
  <h3 class="w3-center">CONTACT</h3>
  <p class="w3-center w3-large">Lets get in touch. Send us a message:</p>
  <div style="margin-top:48px">
    <p><i class="fa fa-map-marker fa-fw w3-xxlarge w3-margin-right"></i> Chicago, US</p>
    <p><i class="fa fa-phone fa-fw w3-xxlarge w3-margin-right"></i> Phone: +00 151515</p>
    <p><i class="fa fa-envelope fa-fw w3-xxlarge w3-margin-right"> </i> Email: mail@mail.com</p>
    <br>
    <form action="/action_page.php" target="_blank">
      <p><input class="w3-input w3-border" type="text" placeholder="Name" required name="Name"></p>
      <p><input class="w3-input w3-border" type="text" placeholder="Email" required name="Email"></p>
      <p><input class="w3-input w3-border" type="text" placeholder="Subject" required name="Subject"></p>
      <p><input class="w3-input w3-border" type="text" placeholder="Message" required name="Message"></p>
      <p>
        <button class="w3-button w3-black" type="submit">
          <i class="fa fa-paper-plane"></i> SEND MESSAGE
        </button>
      </p>
    </form>-->
    <!-- Image of location/map -->
   <!-- <img src="/w3images/map.jpg" class="w3-image w3-greyscale" style="width:100%;margin-top:48px">
  </div>
</div> -->

<!-- Footer -->
<footer class="w3-center w3-black w3-padding-64">
  <a href="#home" class="w3-button w3-light-grey"><i class="fa fa-arrow-up w3-margin-right"></i>To the top</a>
  <div class="w3-xlarge w3-section">
    <i class="fa fa-facebook-official w3-hover-opacity"></i>
    <i class="fa fa-instagram w3-hover-opacity"></i>
    <i class="fa fa-snapchat w3-hover-opacity"></i>
    <i class="fa fa-pinterest-p w3-hover-opacity"></i>
    <i class="fa fa-twitter w3-hover-opacity"></i>
    <i class="fa fa-linkedin w3-hover-opacity"></i>
  </div>
  
</footer>


@stop

@push('scripts')
   
<script>
// Modal Image Gallery
function onClick(element) {
  document.getElementById("img01").src = element.src;
  document.getElementById("modal01").style.display = "block";
  var captionText = document.getElementById("caption");
  captionText.innerHTML = element.alt;
}


// Toggle between showing and hiding the sidebar when clicking the menu icon
var mySidebar = document.getElementById("mySidebar");

function w3_open() {
  if (mySidebar.style.display === 'block') {
    mySidebar.style.display = 'none';
  } else {
    mySidebar.style.display = 'block';
  }
}

// Close the sidebar with the close button
function w3_close() {
    mySidebar.style.display = "none";
}
</script>
@endpush