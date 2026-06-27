<?php


if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (date("m-d", strtotime($merchant->birthday("aes_decrypt"))) == date("m-d")) {

    $getBrowserName = getBrowserName();

    if ($getBrowserName != 'Chrome' AND $getBrowserName != 'Firefox' AND $getBrowserName != 'Opera') {
        $site .= '
        <div class="ui-widget-content" style="height:400px; position:relative; margin-bottom:10px; background-color:#9ccfca; background-image: url('.MCP_URL.'/images/birthday_greetings.jpg); background-position:center; background-repeat: no-repeat;">
            <div style="text-align:center; margin-top:10px; font-weight:600; font-size:20px;">
                Herzlichen Gl&uuml;ckwunsch zum Geburtstag!
            </div>
            <div style="text-align:center; width:600px; line-height:1.2; bottom:10px; position:absolute;">
            Ein Tag der mehr als jeder andere dir ganz privat geh&ouml;rt. Und dennoch m&ouml;chten wir es nicht<br />
            vers&auml;umen, dir als Gesch&auml;ftsfreund f&uuml;r die bisherige Zusammenarbeit zu danken.<br />
            Wir w&uuml;nschen dir weiterhin viel Erfolg und noch eine sch&ouml;ne Geburtstagsfeier.
            </div>
        </div>        
        ';

    } else {

    $site .= '
    <style>

    .text, .fond, .ball {
      position: absolute;
      left: 50%;
      top: 50%;
      transform: translate(-50%, -50%);
    }

    .text {
      position: absolute;
      left: calc(50% - 5px);
      top: 50%;
      transform: translate(-50%, -50%);
    }

    </style>

    <div class="ui-widget-content" style="height:400px; margin-bottom:10px; position:relative; background: radial-gradient(circle, #E3F1E5 60%, #9BCDC9); background-repeat: no-repeat; background-size: auto 100%;">

        <div style="text-align:center; margin-top:10px; font-weight:600; font-size:20px;">
            Herzlichen Gl&uuml;ckwunsch zum Geburtstag!
        </div>
        <div style="text-align:center; width:600px; line-height:1.2; bottom:10px; position:absolute;">
        Ein Tag der mehr als jeder andere dir ganz privat geh&ouml;rt. Und dennoch m&ouml;chten wir es nicht<br />
        vers&auml;umen, dir als Gesch&auml;ftsfreund f&uuml;r die bisherige Zusammenarbeit zu danken.<br />
        Wir w&uuml;nschen dir weiterhin viel Erfolg und noch eine sch&ouml;ne Geburtstagsfeier.
        </div>

        <svg version="1.1" class="ball" x="0px" y="0px" width="600px"
             height="400px" viewBox="0 0 900 700" enable-background="new 0 0 900 700" xml:space="preserve">
            <g>
                    <g id="ball-1">
                                    <radialGradient id="SVGID_1_" cx="751.5518" cy="527.3984" r="37.7212" gradientTransform="matrix(1 -0.0084 0.0084 1 -84.4101 3.5865)" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0" style="stop-color:#FF6C77"/>
                                    <stop  offset="0.611" style="stop-color:#FF333B"/>
                                    <stop  offset="1" style="stop-color:#D83642"/>
                            </radialGradient>
                            <path fill="url(#SVGID_1_)" d="M689.907,534.698c-1.726-20.748-17.176-36.398-34.507-34.956
                                    c-17.332,1.441-29.983,19.43-28.257,40.178c1.674,20.124,16.905,43.21,33.635,43.264c0.071,0.131,0.41,0.795,0.349,1.347
                                    l-0.065,0.005c-0.315,0.026-0.551,0.306-0.525,0.62c0.026,0.315,0.306,0.552,0.621,0.525l2.766-0.23
                                    c0.315-0.025,0.552-0.305,0.525-0.62c-0.025-0.306-0.291-0.533-0.595-0.521c-0.13-0.375-0.232-0.92,0.028-1.384
                                    C680.374,580.11,691.582,554.823,689.907,534.698z"/>
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M662.589,582.862
                                    c0,0-5.854,16.191,3.146,37.191s11.672,13.78,8.599,54.28"/>
          <animateTransform attributeName="transform"
          type="translate"
          dur="2s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
                    </g>
                    <g id="ball-2">
                                    <radialGradient id="SVGID_2_" cx="607.5469" cy="456.9668" r="53.7352" gradientTransform="matrix(0.9998 -0.019 0.019 0.9998 -8.4155 11.1544)" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0" style="stop-color:#C976DD"/>
                                    <stop  offset="0.3403" style="stop-color:#B65CCC"/>
                                    <stop  offset="0.8779" style="stop-color:#7E448C"/>
                            </radialGradient>
                            <path fill="url(#SVGID_2_)" d="M634.236,479.271c2.886-29.534-14.775-55.432-39.447-57.842
                                    c-24.673-2.411-47.013,19.577-49.898,49.111c-2.8,28.648,12.654,64.915,36.1,69.269c0.066,0.203,0.371,1.221,0.145,1.978
                                    l-0.094-0.009c-0.448-0.044-0.851,0.287-0.895,0.735s0.287,0.851,0.735,0.895l3.938,0.385c0.448,0.044,0.851-0.287,0.895-0.735
                                    c0.043-0.436-0.271-0.821-0.7-0.884c-0.087-0.558-0.092-1.35,0.393-1.933C609.254,540.511,631.438,507.92,634.236,479.271z"/>
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M582.805,541.581c0,0-6.857,12.39,0,23.686
                    c6.857,11.295,22.59,46.318,9.682,105.252"/>
          <animateTransform attributeName="transform"
          type="translate"
          dur="3s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>		
        </g>
                    <g id="ball-3">
                            <radialGradient id="SVGID_3_" cx="520.6914" cy="502.8027" r="48.7524" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0.0052" style="stop-color:#FCAE47"/>
                                    <stop  offset="0.5312" style="stop-color:#F39200"/>
                                    <stop  offset="1" style="stop-color:#D67B03"/>
                            </radialGradient>
                            <path fill="url(#SVGID_3_)" d="M544.389,522.695c-0.113-26.905-18.427-48.64-40.903-48.545
                                    c-22.476,0.096-40.604,21.984-40.49,48.89c0.11,26.098,17.386,57.39,38.935,59.159c0.078,0.177,0.447,1.067,0.312,1.771
                                    l-0.084,0.001c-0.409,0.002-0.741,0.337-0.74,0.746c0.002,0.408,0.338,0.74,0.746,0.739l3.588-0.016
                                    c0.408-0.002,0.741-0.337,0.739-0.746c-0.002-0.396-0.32-0.716-0.713-0.732c-0.13-0.495-0.207-1.209,0.176-1.779
                                    C527.488,580.233,544.499,548.795,544.389,522.695z"/>
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M503.936,584.504
                                    c0,0-1.952,12.369,6.022,22.907c20.375,26.923,6.376,48.256-3.624,72.256"/>
          <animateTransform attributeName="transform"
          type="translate"
          dur="2.5s"
          values="0,15;0,-15;0,15"
          repeatCount="indefinite"/>		
        </g>

                            <radialGradient id="ball-5_1_" cx="292.8799" cy="317.4141" r="63.5144" gradientTransform="matrix(0.9527 -0.3039 0.3039 0.9527 -97.3814 104.9306)" gradientUnits="userSpaceOnUse">
                            <stop  offset="0" style="stop-color:#38C6BB"/>
                            <stop  offset="0.4364" style="stop-color:#00A19A"/>
                            <stop  offset="1" style="stop-color:#048279"/>
                    </radialGradient>
                    <path id="ball-5" fill="url(#ball-5_1_)" d="M315.377,340.965c-3.999-34.928-30.895-60.536-60.073-57.194
                            c-29.178,3.34-49.59,34.364-45.591,69.293c3.879,33.88,30.795,72.049,59.036,71.263c0.127,0.218,0.733,1.321,0.659,2.255
                            l-0.11,0.013c-0.53,0.061-0.914,0.544-0.854,1.074s0.544,0.914,1.075,0.854l4.657-0.533c0.53-0.061,0.914-0.544,0.854-1.074
                            c-0.059-0.516-0.519-0.885-1.031-0.85c-0.24-0.625-0.441-1.541-0.026-2.337C301.663,418.113,319.257,374.847,315.377,340.965z">
          <animateTransform attributeName="transform"
          type="translate"
          dur="2s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
        </path>

                            <radialGradient id="ball-6_1_" cx="607.6553" cy="158.7861" r="57.1531" gradientTransform="matrix(0.9967 -0.0807 0.0807 0.9967 -12.2648 45.9037)" gradientUnits="userSpaceOnUse">
                            <stop  offset="0" style="stop-color:#FF6C77"/>
                            <stop  offset="0.611" style="stop-color:#FF333B"/>
                            <stop  offset="1" style="stop-color:#D83642"/>
                    </radialGradient>
                    <path id="ball-6" fill="url(#ball-6_1_)" d="M637.271,176.817c0.504-31.547-20.451-57.463-46.804-57.884
                            c-26.354-0.421-48.126,24.812-48.631,56.359c-0.488,30.6,19.023,67.695,44.245,70.281c0.088,0.209,0.499,1.262,0.324,2.083
                            l-0.1-0.001c-0.479-0.008-0.877,0.378-0.885,0.856c-0.008,0.479,0.378,0.877,0.856,0.885l4.207,0.067
                            c0.479,0.008,0.877-0.378,0.885-0.857c0.007-0.465-0.359-0.847-0.818-0.876c-0.142-0.584-0.214-1.422,0.248-2.082
                            C616.093,243.874,636.783,207.418,637.271,176.817z">
          <animateTransform attributeName="transform"
          type="translate"
          dur="2.5s"
          values="0,15;0,-15;0,15"
          repeatCount="indefinite"/>
        </path>

                            <radialGradient id="ball-8_1_" cx="418.3892" cy="502.3555" r="57.5304" gradientTransform="matrix(0.9976 -0.0688 0.0688 0.9976 -35.7388 39.9006)" gradientUnits="userSpaceOnUse">
                            <stop  offset="0.0105" style="stop-color:#FFD766"/>
                            <stop  offset="0.152" style="stop-color:#FFD03F"/>
                            <stop  offset="0.2988" style="stop-color:#FECA1D"/>
                            <stop  offset="0.4201" style="stop-color:#FEC608"/>
                            <stop  offset="0.5001" style="stop-color:#FEC500"/>
                            <stop  offset="1" style="stop-color:#CE9905"/>
                    </radialGradient>
                    <path id="ball-8" fill="url(#ball-8_1_)" d="M463.631,502.403c-1.113-31.726-23.5-56.689-50.003-55.76
                            c-26.502,0.931-47.084,27.403-45.971,59.128c1.08,30.772,22.591,67.046,48.068,68.349c0.099,0.206,0.566,1.242,0.432,2.077
                            l-0.1,0.003c-0.481,0.017-0.862,0.425-0.845,0.906s0.425,0.862,0.906,0.845l4.23-0.148c0.481-0.017,0.862-0.425,0.845-0.906
                            c-0.016-0.468-0.404-0.833-0.867-0.838c-0.171-0.58-0.288-1.418,0.143-2.104C445.794,570.871,464.711,533.177,463.631,502.403z">
        <animateTransform attributeName="transform"
          type="translate"
          dur="1.5s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
        </path>
                    <radialGradient id="ball-9_1_" cx="370.4746" cy="191.7549" r="69.5904" gradientUnits="userSpaceOnUse">
                            <stop  offset="0" style="stop-color:#38C6BB"/>
                            <stop  offset="0.4364" style="stop-color:#00A19A"/>
                            <stop  offset="1" style="stop-color:#048279"/>
                    </radialGradient>
                    <path id="ball-9" fill="url(#ball-9_1_)" d="M428.56,182.511c0.438-38.402-25.214-69.829-57.293-70.195
                            c-32.08-0.366-58.44,30.469-58.878,68.872c-0.425,37.249,23.533,82.293,54.247,85.299c0.108,0.254,0.614,1.533,0.406,2.534
                            l-0.121-0.001c-0.583-0.006-1.065,0.465-1.072,1.048c-0.006,0.583,0.465,1.065,1.048,1.072l5.12,0.058
                            c0.583,0.007,1.065-0.465,1.072-1.048c0.006-0.566-0.441-1.029-1.001-1.062c-0.175-0.71-0.269-1.73,0.291-2.536
                            C403.156,264.25,428.135,219.761,428.56,182.511z">
          <animateTransform attributeName="transform"
          type="translate"
          dur="2s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
        </path>

                            <radialGradient id="ball-10_1_" cx="646.1289" cy="231.0806" r="56.557" gradientTransform="matrix(0.9994 0.0349 -0.0349 0.9994 13.9334 -19.7496)" gradientUnits="userSpaceOnUse">
                            <stop  offset="0" style="stop-color:#38C6BB"/>
                            <stop  offset="0.4364" style="stop-color:#00A19A"/>
                            <stop  offset="1" style="stop-color:#048279"/>
                    </radialGradient>
                    <path id="ball-10" fill="url(#ball-10_1_)" d="M684.081,253.149c0.957-31.2-19.395-57.14-45.457-57.939
                            c-26.063-0.8-47.968,23.844-48.925,55.043c-0.928,30.262,17.834,67.241,42.746,70.166c0.084,0.208,0.475,1.255,0.289,2.065
                            l-0.098-0.003c-0.474-0.014-0.873,0.361-0.888,0.835s0.361,0.873,0.835,0.888l4.16,0.127c0.474,0.015,0.873-0.361,0.888-0.835
                            c0.014-0.46-0.343-0.843-0.797-0.878c-0.131-0.58-0.191-1.41,0.276-2.056C662.156,319.174,683.152,283.413,684.081,253.149z">
          <animateTransform attributeName="transform"
          type="translate"
          dur="2s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
        </path>
                    <radialGradient id="ball-11_1_" cx="739.7666" cy="221.4639" r="54.7235" gradientUnits="userSpaceOnUse">
                            <stop  offset="0" style="stop-color:#C976DD"/>
                            <stop  offset="0.3403" style="stop-color:#B65CCC"/>
                            <stop  offset="0.8779" style="stop-color:#7E448C"/>
                    </radialGradient>
                    <path id="ball-11" fill="url(#ball-11_1_)" d="M760.185,247.051c3.511-30.017-13.972-56.728-39.047-59.66
                            c-25.075-2.933-48.248,19.024-51.758,49.042c-3.405,29.115,11.628,66.343,35.417,71.229c0.063,0.208,0.354,1.25,0.108,2.016
                            l-0.095-0.011c-0.455-0.053-0.872,0.276-0.925,0.731c-0.054,0.456,0.275,0.872,0.731,0.925l4.002,0.468
                            c0.456,0.053,0.872-0.276,0.926-0.732c0.052-0.442-0.261-0.842-0.696-0.914c-0.078-0.57-0.067-1.375,0.438-1.96
                            C733.562,308.924,756.78,276.168,760.185,247.051z">
          <animateTransform attributeName="transform"
          type="translate"
          dur="3s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
        </path>
                    <radialGradient id="ball-12_1_" cx="519.7061" cy="164.1016" r="69.327" gradientUnits="userSpaceOnUse">
                            <stop  offset="0.0052" style="stop-color:#FCAE47"/>
                            <stop  offset="0.5312" style="stop-color:#F39200"/>
                            <stop  offset="1" style="stop-color:#D67B03"/>
                    </radialGradient>
                    <path id="ball-12" fill="url(#ball-12_1_)" d="M559.578,196.995c-0.162-38.261-26.203-69.168-58.165-69.033
                            s-57.741,31.262-57.579,69.523c0.157,37.112,24.724,81.61,55.366,84.126c0.111,0.251,0.636,1.517,0.443,2.518l-0.12,0
                            c-0.581,0.002-1.055,0.48-1.052,1.061c0.003,0.581,0.479,1.054,1.061,1.052l5.102-0.021c0.581-0.002,1.054-0.48,1.052-1.061
                            c-0.003-0.564-0.455-1.019-1.014-1.042c-0.186-0.705-0.294-1.719,0.25-2.53C535.545,278.815,559.735,234.108,559.578,196.995z">
        <animateTransform attributeName="transform"
          type="translate"
          dur="1.5s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
        </path>
                    <radialGradient id="ball-13_1_" cx="145.7324" cy="143.4155" r="41.1499" gradientUnits="userSpaceOnUse">
                            <stop  offset="0.0052" style="stop-color:#FCAE47"/>
                            <stop  offset="0.5312" style="stop-color:#F39200"/>
                            <stop  offset="1" style="stop-color:#D67B03"/>
                    </radialGradient>
                    <path id="ball-13" fill="url(#ball-13_1_)" d="M170.032,155.78c-1.173-22.677-17.48-40.267-36.424-39.287
                            s-33.35,20.157-32.177,42.835c1.138,21.996,16.953,47.684,35.189,48.313c0.073,0.146,0.419,0.882,0.334,1.48l-0.072,0.003
                            c-0.344,0.018-0.611,0.314-0.593,0.659c0.018,0.344,0.314,0.611,0.658,0.593l3.023-0.156c0.345-0.018,0.612-0.314,0.594-0.658
                            c-0.017-0.334-0.299-0.591-0.63-0.589c-0.129-0.413-0.223-1.011,0.078-1.507C158.087,204.96,171.17,177.777,170.032,155.78z">
          <animateTransform attributeName="transform"
          type="translate"
          dur="3s"
          values="0,25;0,-25;0,25"
          repeatCount="indefinite"/>
        </path>
                            <radialGradient id="ball-14_1_" cx="782.5996" cy="276.4917" r="45.2426" gradientTransform="matrix(0.9398 0.3418 -0.3418 0.9398 153.5421 -238.7914)" gradientUnits="userSpaceOnUse">
                            <stop  offset="0.0052" style="stop-color:#FCAE47"/>
                            <stop  offset="0.5312" style="stop-color:#F39200"/>
                            <stop  offset="1" style="stop-color:#D67B03"/>
                    </radialGradient>
                    <path id="ball-14" fill="url(#ball-14_1_)" d="M808.833,309.546c7.31-23.873-2.931-48.175-22.872-54.282
                            c-19.943-6.107-42.035,8.295-49.346,32.168c-7.091,23.155-0.402,55.641,18.205,63.145c0.021,0.178,0.102,1.068-0.212,1.654
                            l-0.075-0.022c-0.362-0.111-0.75,0.095-0.86,0.457c-0.111,0.362,0.095,0.75,0.457,0.86l3.183,0.975
                            c0.362,0.111,0.75-0.095,0.861-0.457c0.107-0.352-0.087-0.723-0.43-0.846c0.021-0.475,0.149-1.128,0.646-1.528
                            C778.009,355.875,801.742,332.703,808.833,309.546z">
           <animateTransform attributeName="transform"
          type="translate"
          dur="2.5s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
        </path>
                    <radialGradient id="ball-15_1_" cx="267.8477" cy="190.6035" r="60.772" gradientUnits="userSpaceOnUse">
                            <stop  offset="0.0105" style="stop-color:#FFD766"/>
                            <stop  offset="0.152" style="stop-color:#FFD03F"/>
                            <stop  offset="0.2988" style="stop-color:#FECA1D"/>
                            <stop  offset="0.4201" style="stop-color:#FEC608"/>
                            <stop  offset="0.5001" style="stop-color:#FEC500"/>
                            <stop  offset="1" style="stop-color:#CE9905"/>
                    </radialGradient>
                    <path id="ball-15" fill="url(#ball-15_1_)" d="M297.553,217.572c-0.907-33.523-24.344-60.084-52.349-59.327
                            c-28.004,0.758-49.97,28.548-49.063,62.071c0.88,32.516,23.296,71.016,50.198,72.608c0.103,0.218,0.587,1.317,0.439,2.198
                            l-0.106,0.003c-0.509,0.014-0.914,0.441-0.9,0.95c0.014,0.509,0.441,0.914,0.95,0.9l4.47-0.121c0.509-0.014,0.914-0.441,0.9-0.95
                            c-0.013-0.495-0.419-0.884-0.909-0.893c-0.176-0.614-0.292-1.5,0.169-2.222C278.13,289.748,298.433,250.09,297.553,217.572z">
          <animateTransform attributeName="transform"
          type="translate"
          dur="2.5s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
        </path>
                    <path id="ball-17" fill="#E22937" d="M632.432,325.432"/>
                    <g id="ball-18">
                                    <radialGradient id="SVGID_4_" cx="388.9092" cy="530.9316" r="40.5604" gradientTransform="matrix(0.998 -0.063 0.063 0.998 -39.4727 33.0114)" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0" style="stop-color:#38C6BB"/>
                                    <stop  offset="0.4364" style="stop-color:#00A19A"/>
                                    <stop  offset="1" style="stop-color:#048279"/>
                            </radialGradient>
                            <path fill="url(#SVGID_4_)" d="M395.475,557.295c2.949-22.257-9.732-42.297-28.325-44.76
                                    c-18.592-2.464-36.055,13.582-39.004,35.838c-2.86,21.589,7.882,49.412,25.497,53.313c0.044,0.154,0.249,0.933,0.057,1.499
                                    l-0.07-0.01c-0.338-0.045-0.651,0.195-0.696,0.533s0.195,0.651,0.533,0.695l2.968,0.394c0.338,0.045,0.651-0.195,0.696-0.533
                                    c0.043-0.328-0.184-0.628-0.506-0.687c-0.052-0.424-0.034-1.022,0.347-1.451C374.995,602.95,392.615,578.884,395.475,557.295z"/>
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M356.472,601.248c0,0-6.858,12.39,0,23.686
                                    C363.33,636.229,369,647.001,359,695.667"/>
               <animateTransform attributeName="transform"
          type="translate"
          dur="3.5s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
                    </g>
                    <g id="ball-19">
                            <radialGradient id="SVGID_5_" cx="260.8477" cy="528.5" r="40.6015" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0.0052" style="stop-color:#FCAE47"/>
                                    <stop  offset="0.5312" style="stop-color:#F39200"/>
                                    <stop  offset="1" style="stop-color:#D67B03"/>
                            </radialGradient>
                            <path fill="url(#SVGID_5_)" d="M280.741,541.581c-0.095-22.407-15.346-40.509-34.065-40.429
                                    c-18.718,0.079-33.816,18.308-33.721,40.716c0.092,21.734,14.479,47.795,32.425,49.269c0.065,0.146,0.373,0.889,0.26,1.475h-0.07
                                    c-0.34,0.001-0.618,0.281-0.616,0.621c0.001,0.34,0.281,0.617,0.621,0.616l2.987-0.013c0.34-0.002,0.618-0.281,0.616-0.621
                                    c-0.001-0.331-0.267-0.597-0.594-0.61c-0.108-0.413-0.172-1.007,0.146-1.482C266.665,589.499,280.833,563.316,280.741,541.581z"
                                    />
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M247.472,590.248c0,0-6.858,12.39,0,23.686
                                    c6.858,11.295,10.862,28.4-10.472,65.733"/>
          <animateTransform attributeName="transform"
          type="translate"
          dur="1.5s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
                    </g>
                    <g id="ball-20">
                            <radialGradient id="SVGID_6_" cx="301.7573" cy="468.998" r="47.876" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0" style="stop-color:#FF6C77"/>
                                    <stop  offset="0.611" style="stop-color:#FF333B"/>
                                    <stop  offset="1" style="stop-color:#D83642"/>
                            </radialGradient>
                            <path fill="url(#SVGID_6_)" d="M327.818,479.756c-5.807-25.886-28.044-42.937-49.668-38.085s-34.445,29.768-28.637,55.653
                                    c5.633,25.107,28.895,51.581,50.021,48.722c0.113,0.154,0.656,0.934,0.675,1.64l-0.082,0.018
                                    c-0.393,0.089-0.642,0.482-0.554,0.875c0.088,0.394,0.481,0.643,0.875,0.555l3.452-0.774c0.393-0.089,0.642-0.482,0.554-0.875
                                    c-0.086-0.382-0.46-0.622-0.842-0.555c-0.23-0.449-0.456-1.12-0.207-1.751C323.729,538.74,333.452,504.864,327.818,479.756z"/>
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M299.965,546.359c0,0,6.61,6.695,5.803,17.991
                                    c-0.807,11.295-13.803,41.954-2.868,71.402"/>
          <animateTransform attributeName="transform"
          type="translate"
          dur="2.5s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
                    </g>
                    <g id="ball-21">
                                    <radialGradient id="SVGID_7_" cx="864.7441" cy="148.4692" r="40.3353" gradientTransform="matrix(1 0.0014 -0.0014 1 -5.1974 -0.2258)" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0" style="stop-color:#C976DD"/>
                                    <stop  offset="0.3403" style="stop-color:#B65CCC"/>
                                    <stop  offset="0.8779" style="stop-color:#7E448C"/>
                            </radialGradient>
                            <path fill="url(#SVGID_7_)" d="M877.819,165.723c2.618-22.121-10.239-41.827-28.719-44.015s-35.583,13.972-38.201,36.093
                                    c-2.54,21.457,8.502,48.912,26.031,52.539c0.046,0.153,0.259,0.922,0.077,1.486l-0.069-0.008
                                    c-0.336-0.04-0.644,0.203-0.684,0.539c-0.039,0.335,0.203,0.643,0.539,0.683l2.949,0.349c0.336,0.04,0.644-0.203,0.683-0.538
                                    c0.039-0.326-0.19-0.621-0.512-0.674c-0.057-0.42-0.048-1.014,0.324-1.444C858.131,211.3,875.278,187.181,877.819,165.723z"/>
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M838.246,211.907c0,0,5.523,7.616,3.021,18.661
                                    s-19.976,39.391-13.608,70.152"/>
          <animateTransform attributeName="transform"
          type="translate"
          dur="2s"
          values="0,15;0,-15;0,15"
          repeatCount="indefinite"/>
                    </g>
                    <g id="ball-22">
                                    <radialGradient id="SVGID_8_" cx="148.5864" cy="410.4863" r="40.9925" gradientTransform="matrix(0.9922 -0.1243 0.1243 0.9922 -51.9067 17.6155)" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0.0105" style="stop-color:#FFD766"/>
                                    <stop  offset="0.152" style="stop-color:#FFD03F"/>
                                    <stop  offset="0.2988" style="stop-color:#FECA1D"/>
                                    <stop  offset="0.4201" style="stop-color:#FEC608"/>
                                    <stop  offset="0.5001" style="stop-color:#FEC500"/>
                                    <stop  offset="1" style="stop-color:#CE9905"/>
                            </radialGradient>
                            <path fill="url(#SVGID_8_)" d="M165.872,418.333c-2.185-22.514-19.202-39.285-38.009-37.46s-32.282,21.556-30.097,44.069
                                    c2.119,21.837,19.01,46.689,37.186,46.497c0.079,0.143,0.457,0.858,0.398,1.458l-0.071,0.007
                                    c-0.342,0.033-0.594,0.34-0.561,0.682c0.033,0.342,0.34,0.595,0.682,0.562l3.002-0.291c0.341-0.033,0.594-0.341,0.561-0.683
                                    c-0.032-0.332-0.324-0.574-0.654-0.558c-0.147-0.404-0.267-0.996,0.009-1.503C156.193,467.809,167.992,440.171,165.872,418.333z"
                                    />
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M137.386,472.113c0,0,6.769,6.535,6.232,17.847
                                    s-12.794,42.272-1.157,71.451"/>
          <animateTransform attributeName="transform"
          type="translate"
          dur="2.5s"
          values="0,5;0,-5;0,5"
          repeatCount="indefinite"/>
                    </g>
                    <g id="ball-23">
                                    <radialGradient id="SVGID_9_" cx="176.1055" cy="327.4487" r="57.4858" gradientTransform="matrix(0.998 -0.063 0.063 0.998 -22.4063 11.1486)" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0" style="stop-color:#C976DD"/>
                                    <stop  offset="0.3403" style="stop-color:#B65CCC"/>
                                    <stop  offset="0.8779" style="stop-color:#7E448C"/>
                            </radialGradient>
                            <path fill="url(#SVGID_9_)" d="M202.382,348.205c-1.638-31.682-24.42-56.255-50.886-54.887s-46.593,28.16-44.955,59.842
                                    c1.589,30.73,23.684,66.618,49.161,67.498c0.102,0.204,0.586,1.231,0.466,2.067l-0.1,0.006c-0.481,0.024-0.854,0.438-0.829,0.92
                                    c0.025,0.48,0.438,0.854,0.919,0.829l4.225-0.219c0.481-0.024,0.854-0.438,0.829-0.92c-0.024-0.467-0.417-0.825-0.88-0.823
                                    c-0.181-0.576-0.312-1.412,0.108-2.105C185.693,416.913,203.971,378.936,202.382,348.205z"/>
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M158.472,421.248c0,0-6.858,12.39,0,23.686 c6.858,11.295,22.59,46.318,9.682,105.252"/>
          <animateTransform attributeName="transform"
          type="translate"
          dur="2.5s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
                    </g>
                    <g id="ball-24">
                                    <radialGradient id="SVGID_10_" cx="708.6006" cy="342.7559" r="47.5949" gradientTransform="matrix(0.9634 0.2681 -0.2681 0.9634 120.0183 -166.6286)" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0.0105" style="stop-color:#FFD766"/>
                                    <stop  offset="0.152" style="stop-color:#FFD03F"/>
                                    <stop  offset="0.2988" style="stop-color:#FECA1D"/>
                                    <stop  offset="0.4201" style="stop-color:#FEC608"/>
                                    <stop  offset="0.5001" style="stop-color:#FEC500"/>
                                    <stop  offset="1" style="stop-color:#CE9905"/>
                            </radialGradient>
                            <path fill="url(#SVGID_10_)" d="M732.155,372.637c6.354-25.483-5.752-50.446-27.04-55.754
                                    c-21.289-5.309-43.697,11.047-50.052,36.531c-6.164,24.718,2.665,58.474,22.628,65.323c0.031,0.187,0.167,1.117-0.13,1.75
                                    l-0.081-0.02c-0.387-0.097-0.782,0.141-0.878,0.527c-0.097,0.388,0.141,0.783,0.527,0.879l3.398,0.848
                                    c0.387,0.097,0.782-0.142,0.879-0.528c0.093-0.375-0.132-0.754-0.499-0.864c-0.004-0.5,0.095-1.193,0.594-1.642
                                    C702.345,423.016,725.991,397.356,732.155,372.637z"/>
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M679.472,418.248c0,0-6.858,12.39,0,23.686
                                    c6.858,11.295,22.591,46.318,9.682,105.252"/>
          <animateTransform attributeName="transform"
          type="translate"
          dur="3s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
                    </g>
                    <g id="ball-25">
                                    <radialGradient id="SVGID_11_" cx="65.1016" cy="199.8833" r="44.9043" gradientTransform="matrix(0.9986 -0.0531 0.0531 0.9986 -2.8141 11.0201)" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0" style="stop-color:#FF6C77"/>
                                    <stop  offset="0.611" style="stop-color:#FF333B"/>
                                    <stop  offset="1" style="stop-color:#D83642"/>
                            </radialGradient>
                            <path fill="url(#SVGID_11_)" d="M92.649,218.016c-3.157-24.582-22.364-42.371-42.898-39.733
                                    c-20.535,2.638-34.622,24.703-31.464,49.285c3.063,23.843,22.405,50.487,42.303,49.659c0.091,0.153,0.529,0.924,0.486,1.583
                                    l-0.078,0.01c-0.373,0.048-0.639,0.393-0.591,0.766s0.393,0.64,0.766,0.591l3.278-0.421c0.373-0.048,0.639-0.393,0.591-0.766
                                    c-0.046-0.362-0.374-0.618-0.735-0.589c-0.175-0.438-0.326-1.082-0.041-1.646C83.729,272.528,95.712,241.86,92.649,218.016z"/>
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M63.307,277.293c0,0-5.854,16.192,3.146,37.192
                                    s26.146,42,23.073,82.5"/>
          <animateTransform attributeName="transform"
          type="translate"
          dur="2s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
                    </g>
                    <g id="ball-26">
                            <radialGradient id="SVGID_12_" cx="773.7656" cy="397.4434" r="60.5048" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0" style="stop-color:#38C6BB"/>
                                    <stop  offset="0.4364" style="stop-color:#00A19A"/>
                                    <stop  offset="1" style="stop-color:#048279"/>
                            </radialGradient>
                            <path fill="url(#SVGID_12_)" d="M797.544,428.965c6.482-32.857-10.515-63.884-37.962-69.299
                                    c-27.449-5.415-54.954,16.832-61.437,49.689c-6.287,31.871,7.091,74.304,32.949,81.766c0.052,0.235,0.282,1.412-0.056,2.237
                                    l-0.104-0.021c-0.499-0.099-0.987,0.229-1.086,0.728c-0.098,0.499,0.229,0.987,0.729,1.086l4.381,0.864
                                    c0.499,0.099,0.987-0.229,1.086-0.728c0.096-0.484-0.215-0.953-0.689-1.07c-0.037-0.636,0.045-1.525,0.652-2.127
                                    C762.764,495.011,791.257,460.837,797.544,428.965z"/>
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M733.846,494c0,0-4.846,9.667-2.513,21.667
                                    c2.334,12,12.334,69.334-16.999,133.334"/>
          <animateTransform attributeName="transform"
          type="translate"
          dur="2s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
                    </g>
                    <g id="ball-27">
                                    <radialGradient id="SVGID_13_" cx="895.8691" cy="350.4355" r="35.8536" gradientTransform="matrix(0.9975 0.0707 -0.0707 0.9975 11.1838 -46.4745)" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0.0105" style="stop-color:#FFD766"/>
                                    <stop  offset="0.152" style="stop-color:#FFD03F"/>
                                    <stop  offset="0.2988" style="stop-color:#FECA1D"/>
                                    <stop  offset="0.4201" style="stop-color:#FEC608"/>
                                    <stop  offset="0.5001" style="stop-color:#FEC500"/>
                                    <stop  offset="1" style="stop-color:#CE9905"/>
                            </radialGradient>
                            <path fill="url(#SVGID_13_)" d="M898.18,377.276c1.948-19.688-9.805-36.969-26.252-38.597
                                    c-16.447-1.628-31.36,13.013-33.309,32.701c-1.891,19.098,8.382,43.29,24.01,46.212c0.045,0.136,0.247,0.814,0.095,1.319
                                    l-0.062-0.007c-0.299-0.029-0.568,0.19-0.598,0.489s0.19,0.568,0.489,0.598l2.626,0.26c0.299,0.029,0.567-0.19,0.597-0.489
                                    c0.029-0.29-0.18-0.548-0.466-0.591c-0.058-0.371-0.061-0.898,0.264-1.287C881.472,418.085,896.289,396.375,898.18,377.276z"/>
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M864.228,418.265c0,0-2.423,4.833-1.256,10.833
                                    c1.166,6,5,50-9.667,82"/>
          <animateTransform attributeName="transform"
          type="translate"
          dur="3s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
                    </g>
                    <g id="ball-29">
                                    <radialGradient id="SVGID_14_" cx="68.502" cy="371.0752" r="36.368" gradientTransform="matrix(0.998 -0.063 0.063 0.998 -39.4727 33.0114)" gradientUnits="userSpaceOnUse">
                                    <stop  offset="0" style="stop-color:#38C6BB"/>
                                    <stop  offset="0.4364" style="stop-color:#00A19A"/>
                                    <stop  offset="1" style="stop-color:#048279"/>
                            </radialGradient>
                            <path fill="url(#SVGID_14_)" d="M64.256,415.988c2.645-19.956-8.727-37.924-25.397-40.132
                                    c-16.67-2.21-32.328,12.177-34.972,32.134c-2.564,19.356,7.067,44.304,22.862,47.801c0.04,0.138,0.223,0.836,0.051,1.344
                                    l-0.063-0.009c-0.303-0.039-0.583,0.175-0.624,0.479c-0.04,0.303,0.175,0.584,0.478,0.623l2.662,0.354
                                    c0.302,0.039,0.583-0.176,0.624-0.478c0.039-0.296-0.165-0.563-0.454-0.617c-0.046-0.379-0.031-0.916,0.311-1.3
                                    C45.893,456.925,61.691,435.347,64.256,415.988z"/>
                            <path fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-miterlimit="10" d="M27.987,457.4c0,0-4.596,8.304,0,15.873			c4.596,7.57,15.14,31.041,6.488,70.537"/>
                    <animateTransform attributeName="transform"
          type="translate"
          dur="1.5s"
          values="0,10;0,-10;0,10"
          repeatCount="indefinite"/>
        </g>
            </g>
        </svg>

        <svg version="1.1" class="fond" x="0px" y="0px" width="226px" height="226px" viewBox="0 0 426 426" enable-background="new 0 0 426 426" xml:space="preserve">
          <circle class="round" fill="#FFFFFF" stroke-miterlimit="10" cx="207.941" cy="213.232" r="197.5" filter="url(#inset-shadow)"/>
            <filter id="inset-shadow">
            <feGaussianBlur in="SourceAlpha" stdDeviation="5"/>
            <feOffset dx="5" dy="0" result="offsetblur"/>
            <feFlood flood-color="rgba(42,68,60,0.5)"/>
            <feComposite in2="offsetblur" operator="in"/>
            <feMerge>
            <feMergeNode/>
            <feMergeNode in="SourceGraphic"/>
            </feMerge>
          </filter>
        </svg>

        <svg version="1.1" class="text" x="0px" y="0px" width="160px" height="180px" viewBox="0 0 250 210" enable-background="new 0 0 250 210" xml:space="preserve">

          <path class="happy-h-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="50" d="M34.188,9.297c0,0,1.583-2.104,3.958-2.104c3.563,0,3.959,2.812,3.959,5.375c0,1.813,0,37.354,0,37.354">
            <animate id="first"
            attributeName="stroke-dashoffset"
            from="50" to="0"
            dur="1s" 
            repeatCount="1"
            repeatCount ="indefinite"
            begin="0s"/>
          </path>

          <line class="happy-h-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10"  stroke-dasharray="28" opacity="0" x1="42.104" y1="26.297" x2="70.021" y2="26.297">
            <animate attributeName="stroke-dashoffset"
            from="28" to="0"
            dur="1s"
            begin="first.end"/>
            <animate attributeName="opacity" values="1" begin="first.end"/>
          </line>

          <line class="happy-h-3" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="46" x1="70.021" y1="4.839" x2="70.021" y2="49.922">
           <animate attributeName="stroke-dashoffset"
            from="-46" to="0"
            dur="1s"
            begin="first.begin"/>
          </line>

          <path class="happy-a-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="69" opacity="0" d="M105.438,20.255c0,0-7.521-2.688-12.333-1.667c-5.438,1.154-11,4.5-10.583,15.583c0.417,11.083,6.75,14.104,11.75,14.271
          c5,0.167,11-4.188,11.167-7.354">
            <animate id="second" attributeName="stroke-dashoffset"
            from="69" to="0"
            dur="1s"
            begin="first.end-0.5s"/>
            <animate attributeName="opacity" values="1" begin="first.end-0.5s"/>
          </path>

          <path class="happy-a-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="40" opacity="0" d="
          M105.438,16.359v29.313c0,0,0.802,2.779,3.677,2.775c2.69-0.004,3.625-1.682,4.125-2.432">
            <animate attributeName="stroke-dashoffset"
            from="40" to="0"
            dur="1s"
            begin="second.end"/>
            <animate attributeName="opacity" values="1" begin="second.end"/>
          </path>

          <path class="happy-p1-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="51" opacity="0" d=" M118.521,17.172c0,0,3.469,3.313,3.469,7.313c0,1.354,0,42.688,0,42.688">
            <animate id="third" attributeName="stroke-dashoffset"
            from="51" to="0"
            dur="1s"
            begin="second.end-0.5s"/>
            <animate attributeName="opacity" values="1" begin="second.end-0.5s"/>
          </path>

          <path class="happy-p1-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="70" opacity="0" d="
          M121.99,26.047c0,0,4.281-7.625,10.948-7.625c7.542,0,12.167,5.583,12.167,14.884c0,6.866-3,15.141-13.333,15.141
          c-9.25,0-9.781-3.463-9.781-3.463">
            <animate attributeName="stroke-dashoffset"
            from="70" to="0"
            dur="1s"
            begin="third.end"/>
            <animate attributeName="opacity" values="1" begin="third.end"/>
          </path>

          <path class="happy-p2-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="51" d="
          M157.647,67.172c0,0,0-41.333,0-42.688c0-4.063-3.469-7.313-3.469-7.313">
           <animate attributeName="stroke-dashoffset"
            from="-51" to="0"
            dur="1s"
            begin="first.begin"/>
          </path>

          <path class="happy-p2-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="70" opacity="0" d="
          M157.647,26.047c0,0,4.281-7.625,10.948-7.625c7.542,0,12.167,5.583,12.167,14.884c0,6.866-3,15.141-13.333,15.141
          c-9.25,0-9.781-3.463-9.781-3.463">
            <animate attributeName="stroke-dashoffset"
            from="70" to="0"
            dur="1s"
            begin="first.end"/>
            <animate attributeName="opacity" values="1" begin="first.end"/>
          </path>

          <path class="happy-y-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="55" opacity="0" d="  M188.813,17.443c0,0,4.708,2.979,4.708,6.271c0,2.167,0,14.708,0,16.375c0,1.667,0.333,8.359,9.333,8.359
          c6.167,0,11.932-5.911,11.932-8.744">
            <animate attributeName="stroke-dashoffset"
            from="55" to="0"
            dur="1s"
            begin="first.end-0.5s"/>
            <animate attributeName="opacity" values="1" begin="first.end-0.5s"/>
          </path>

          <path class="happy-y-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="81" opacity="0" d="
          M214.786,16.505c0,0,0,38.792,0,40.167c0,1.688-0.432,9.333-7.849,9.333c-5.138,0-7.667-5-7.667-8.167
          c0-4.229,2.938-8.606,8.719-10.667">
            <animate attributeName="stroke-dashoffset"
            from="81" to="0"
            dur="1s"
            begin="second.end"/>
            <animate attributeName="opacity" values="1" begin="second.end"/>
          </path>

          <line class="birth-b-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="47" opacity="0" x1="9.479" y1="75.589" x2="9.479" y2="121.922">
            <animate attributeName="stroke-dashoffset"
            from="47" to="0"
            dur="1s"
            begin="second.end-0.5s"/>
            <animate attributeName="opacity" values="1" begin="second.end-0.5s"/>
          </line>

          <path class="birth-b-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="70"opacity="0"  d="
          M9.479,100.005c0,0,3.042-8,11.292-8s12.083,6.75,12.083,14.917c0,8.333-4.938,15-14.021,15s-9.422-0.984-9.422-0.984">
            <animate attributeName="stroke-dashoffset"
            from="70" to="0"
            dur="1s"
            begin="third.end"/>
            <animate attributeName="opacity" values="1" begin="third.end"/>
          </path>

          <path class="birth-i-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="40" d="
          M51.979,119.818c0,0-1.583,2.104-3.958,2.104c-3.563,0-3.959-2.813-3.959-5.375c0-1.813,0-27.042,0-27.042">
           <animate attributeName="stroke-dashoffset"
            from="-40" to="0"
            dur="1s"
            begin="first.begin"/>
          </path>

          <circle class="birth-i-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="7" opacity="0" cx="44.063" cy="79.641" r="1">
            <animate attributeName="stroke-dashoffset"
            from="7" to="0"
            dur="1s"
            begin="first.end"/>
            <animate attributeName="opacity" values="1" begin="first.end"/>
          </circle>

          <path class="birth-r-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="34" opacity="0" d="
          M60.179,123.505c0,0,0-23.769,0-25.124c0-4.063-3.469-7.313-3.469-7.313">
            <animate attributeName="stroke-dashoffset"
            from="-34" to="0"
            dur="1s"
            begin="first.end-0.5s"/>
            <animate attributeName="opacity" values="1" begin="first.end-0.5s"/>
          </path>

          <path class="birth-r-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="19" opacity="0" d="
          M60.179,100.13c0,0,4.102-8.146,9.935-8.146c2.417,0,5.406,0.406,5.406,0.406">
            <animate attributeName="stroke-dashoffset"
            from="19" to="0"
            dur="1s"
            begin="second.end"/>
            <animate attributeName="opacity" values="1" begin="second.end"/>
          </path>

          <path class="birth-t-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="54" opacity="0" d="
          M95.813,117.172c0,0-3.25,4.75-7.188,4.75c-4.313,0-6.854-1.667-6.854-7.917c0-1.813,0-32,0-32">
            <animate attributeName="stroke-dashoffset"
            from="-54" to="0"
            dur="1s"
            begin="second.end-0.5s"/>
            <animate attributeName="opacity" values="1" begin="second.end-0.5s"/>
          </path>

          <line class="birth-t-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="13" opacity="0" x1="81.771" y1="92.531" x2="94.24" y2="92.531">
            <animate attributeName="stroke-dashoffset"
            from="13" to="0"
            dur="1s"
            begin="third.end"/>
            <animate attributeName="opacity" values="1" begin="third.end"/>
          </line>

          <line class="birth-h-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="48" x1="105.177" y1="75.589" x2="105.177" y2="123.505">
           <animate attributeName="stroke-dashoffset"
            from="48" to="0"
            dur="1s"
            begin="first.begin"/>
          </line>

          <path class="birth-h-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="58" opacity="0" d="
          M105.177,100.297c0,0,4.531-8.219,12.406-8.219c8.406,0,8.938,6.469,8.938,8.031s0,17,0,17s-0.083,4.375,3.417,4.375
          c2.208,0,4.208-2.521,4.208-2.521">
            <animate attributeName="stroke-dashoffset"
            from="58" to="0"
            dur="1s"
            begin="first.end"/>
            <animate attributeName="opacity" values="1" begin="first.end"/>
          </path>

          <path class="birth-d-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="69" opacity="0" d="
          M164.049,93.002c0,0-6.917-1.917-12.333-0.667s-11.158,4.5-10.742,15.583c0.417,11.083,6.908,13.67,11.908,13.837
          c5,0.167,11-4.229,11.167-7.396">
            <animate attributeName="stroke-dashoffset"
            from="69" to="0"
            dur="1s"
            begin="first.end-0.5s"/>
            <animate attributeName="opacity" values="1" begin="first.end-0.5s"/>
          </path>

          <path class="birth-d-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="54" opacity="0" d="
          M171.965,119.818c0,0-1.583,2.104-3.958,2.104c-3.563,0-3.959-2.813-3.959-5.375c0-1.813,0-40.958,0-40.958">
            <animate attributeName="stroke-dashoffset"
            from="-54" to="0"
            dur="1s"
            begin="second.end"/>
            <animate attributeName="opacity" values="1" begin="second.end"/>
          </path>

          <path class="birth-a-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="69" opacity="0" d="
          M201.409,93.876c0,0-7.521-2.688-12.333-1.667c-5.438,1.154-11,4.5-10.583,15.583c0.417,11.083,6.75,14.104,11.75,14.271
          s11-4.188,11.167-7.354">
            <animate attributeName="stroke-dashoffset"
            from="69" to="0"
            dur="1s"
            begin="second.end-0.5s"/>
            <animate attributeName="opacity" values="1" begin="second.end-0.5s"/>
          </path>

          <path class="birth-a-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="40" opacity="0" d="
          M201.409,89.98v29.313c0,0,0.802,2.779,3.677,2.775c2.69-0.004,3.625-1.682,4.125-2.432">
            <animate attributeName="stroke-dashoffset"
            from="40" to="0"
            dur="1s"
            begin="third.end"/>
            <animate attributeName="opacity" values="1" begin="third.end"/>
          </path>

          <path class="birth-y-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="55" d="M214.464,90.918c0,0,4.708,2.979,4.708,6.271c0,2.167,0,14.708,0,16.375s0.333,8.359,9.333,8.359c6.167,0,11.932-5.911,11.932-8.744">
           <animate attributeName="stroke-dashoffset"
            from="55" to="0"
            dur="1s"
            begin="first.begin"/>
          </path>

          <path class="birth-y-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="81" opacity="0" d="
          M240.438,89.98c0,0,0,38.792,0,40.167c0,1.688-0.432,9.333-7.849,9.333c-5.138,0-7.667-5-7.667-8.167
          c0-4.229,2.938-8.606,8.719-10.667">
            <animate attributeName="stroke-dashoffset"
            from="81" to="0"
            dur="1s"
            begin="first.end"/>
            <animate attributeName="opacity" values="1" begin="first.end"/>
          </path>

          <path class="to-t-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="53" d="M41,180.469
          c0,0-3.25,4.75-7.188,4.75c-4.312,0-6.854-1.667-6.854-7.917c0-1.813,0-32,0-32">
           <animate attributeName="stroke-dashoffset"
            from="-53" to="0"
            dur="1s"
            begin="first.begin"/>
          </path>

          <line class="to-t-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="13" opacity="0" x1="26.958" y1="155.828" x2="39.427" y2="155.828">
            <animate attributeName="stroke-dashoffset"
            from="13" to="0"
            dur="1s"
            begin="first.end"/>
            <animate attributeName="opacity" values="1" begin="first.end"/>
          </line>

          <path class="to-o" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="93" opacity="0" d="M62.677,155.172
          c10.677,0,14.344,8.625,14.344,15.167c0,6.208-3.375,14.88-14.25,14.88c-10.458,0-14-8.88-14-15.023
          c0-6.482,4.333-15.023,14.344-15.023">
            <animate attributeName="stroke-dashoffset"
            from="93" to="0"
            dur="1s"
            begin="third.end-0.5s"/>
            <animate attributeName="opacity" values="1" begin="third.end-0.5s"/>
          </path>

          <path class="you-y-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="53" d="  M104.859,154.214c0,0,4.708,2.979,4.708,6.271c0,2.167,0,14.708,0,16.375c0,1.667,0.333,8.359,9.333,8.359
          c6.167,0,11.932-5.911,11.932-8.744">
           <animate attributeName="stroke-dashoffset"
            from="53" to="0"
            dur="1s"
            begin="first.begin"/>
          </path>

          <path class="you-y-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="81" opacity="0" d="
          M130.833,153.277c0,0,0,38.792,0,40.167c0,1.688-0.432,9.333-7.849,9.333c-5.138,0-7.667-5-7.667-8.167
          c0-4.229,2.938-8.606,8.719-10.667">
            <animate attributeName="stroke-dashoffset"
            from="81" to="0"
            dur="1s"
            begin="first.end"/>
            <animate attributeName="opacity" values="1" begin="first.end"/>
          </path>

          <path class="you-o" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="93" opacity="0" d="
          M156.732,155.172c10.677,0,14.344,8.625,14.344,15.167c0,6.208-3.375,14.88-14.25,14.88c-10.458,0-14-8.88-14-15.023
          c0-6.482,4.333-15.023,14.344-15.023">
            <animate attributeName="stroke-dashoffset"
            from="93" to="0"
            dur="1s"
            begin="second.end"/>
            <animate attributeName="opacity" values="1" begin="second.end"/>
          </path>

          <path class="you-u-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="55" d="  M179.729,154.214c0,0,4.708,2.979,4.708,6.271c0,2.167,0,14.708,0,16.375c0,1.667,0.333,8.359,9.333,8.359
          c6.167,0,11.932-5.911,11.932-8.744">
           <animate attributeName="stroke-dashoffset"
            from="55" to="0"
            dur="1s"
            begin="first.begin"/>
          </path>

          <path class="you-u-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="39" opacity="0" d="
          M213.62,183.115c0,0-1.583,2.104-3.958,2.104c-3.563,0-3.959-2.813-3.959-5.375c0-1.813,0-26.672,0-26.672">
            <animate attributeName="stroke-dashoffset"
            from="-39" to="0"
            dur="1s"
            begin="first.end"/>
            <animate attributeName="opacity" values="1" begin="first.end"/>
          </path>

          <line class="you-point-1" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="33" opacity="0" x1="222.146" y1="145.547" x2="222.146" y2="178.297">
            <animate attributeName="stroke-dashoffset"
            from="-33" to="0"
            dur="1s"
            begin="third.end"/>
            <animate attributeName="opacity" values="1" begin="third.end"/>
          </line>

          <circle class="you-point-2" fill="none" stroke="#00A19A" stroke-width="5" stroke-linejoin="round" stroke-miterlimit="10" stroke-dasharray="7" opacity="0" cx="222.146" cy="184.18" r="1">
            <animate attributeName="stroke-dashoffset"
            from="7" to="0"
            dur="1s"
            begin="second.end-0.5s"/>
            <animate attributeName="opacity" values="1" begin="second.end-0.5s"/>
          </circle>
        </svg>
    </div>
    ';
    }
}