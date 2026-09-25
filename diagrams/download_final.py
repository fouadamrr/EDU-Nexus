import base64
import urllib.request
import os

diagrams = {
    'erd_diagram.png': '''%%{init: {"theme": "default", "themeVariables": {"fontSize": "24px"}}}%%
    erDiagram
    COLLEGES ||--o{ DEPARTMENTS : contains
    COLLEGES ||--o{ COURSES : offers
    COLLEGES ||--o{ CLASSROOMS : has
    ROLES ||--o{ ROLE_PERMISSIONS : has
    ROLES ||--o{ USERS : defines
    USERS ||--o| STUDENTS : extends
    USERS ||--o| PROFESSORS : extends
    USERS ||--o| ADMINS : extends
    COURSES ||--o{ COURSE_PREREQUISITES : requires
    USERS ||--o{ ENROLLMENTS : registers
    COURSES ||--o{ ENROLLMENTS : includes
    USERS ||--o{ GRADES : earns
    COURSES ||--o{ GRADES : for
    USERS ||--o{ ATTENDANCE : marked
    COURSES ||--o{ ASSIGNMENTS : has
    ASSIGNMENTS ||--o{ SUBMISSIONS : receives
    USERS ||--o{ SUBMISSIONS : submits
    USERS ||--o{ FEES : pays
    COURSES ||--o{ EXAMS : has
    EXAMS ||--o{ EXAM_GRADES : results
    USERS ||--o{ EXAM_GRADES : achieves
    COURSES ||--o{ ONLINE_EXAMS : has_online
    ONLINE_EXAMS ||--o{ ONLINE_SUBMISSIONS : takes
    USERS ||--o{ ONLINE_SUBMISSIONS : makes
    ONLINE_SUBMISSIONS ||--o{ ONLINE_ANSWERS : answers
    ''',

    'use_case_diagram.png': '''%%{init: {"theme": "default", "themeVariables": {"fontSize": "24px"}}}%%
    flowchart LR
    Student((Student))
    Professor((Professor))
    Affairs((Student Affairs))
    Admin((System Admin))

    subgraph EDU_Nexus
        UC1[Register Courses]
        UC2[Take Online Exams]
        UC3[View Grades]
        UC4[Pay Fees]
        UC5[Upload Resources]
        UC6[Mark Attendance]
        UC7[Grade Submissions]
        UC8[Manage Student Data]
        UC9[Manage Schedules]
        UC10[Manage Colleges]
        UC11[Assign Roles]
    end

    Student --> UC1
    Student --> UC2
    Student --> UC3
    Student --> UC4
    Professor --> UC5
    Professor --> UC6
    Professor --> UC7
    Affairs --> UC8
    Affairs --> UC9
    Admin --> UC10
    Admin --> UC11
    ''',

    'dfd_context_.png': '''%%{init: {"theme": "default", "themeVariables": {"fontSize": "24px"}}}%%
    flowchart TD
    Student[Student]
    Prof[Professor]
    Admin[Admin]
    Gateway[Payment Gateway]
    System((EDU Nexus))
    Student -- Registration, Exams --> System
    System -- Grades, Alerts --> Student
    Prof -- Materials, Grades --> System
    System -- Submissions --> Prof
    Admin -- Configs --> System
    System -- Reports --> Admin
    System -- Payment Req --> Gateway
    Gateway -- Receipt --> System
    ''',

    'dfd_level_0_.png': '''%%{init: {"theme": "default", "themeVariables": {"fontSize": "24px"}}}%%
    flowchart TD
    Student[Student]
    Prof[Professor]
    Gateway[Payment Gateway]

    P1((1. Manage Users))
    P2((2. Course Registration))
    P3((3. Academic Evaluation))
    P4((4. Financial Management))

    D1[(User DB)]
    D2[(Academic DB)]
    D3[(Finance DB)]

    Student -->|Credentials| P1
    P1 -->|Auth Info| D1
    
    Student -->|Course Requests| P2
    P2 -->|Enrollment| D2
    
    Prof -->|Grades, Exams| P3
    P3 -->|Scores| D2
    D2 -->|Results| Student
    
    Student -->|Payment| P4
    P4 -->|Transaction| Gateway
    P4 -->|Receipt| D3
    ''',

    'state_diagram.png': '''%%{init: {"theme": "default", "themeVariables": {"fontSize": "24px"}}}%%
    stateDiagram-v2
    [*] --> Active : Account Created
    Active --> Enrolled : Registers Courses
    Enrolled --> Active : Term Ends
    Enrolled --> AcademicWarning : Low GPA
    AcademicWarning --> Active : GPA Improved
    AcademicWarning --> Suspended : Unpaid Fees / Failed
    Suspended --> Active : Appeal / Paid
    Active --> Graduated : Passed All Credits
    Graduated --> [*]
    ''',

    'flowchart_mockup.png': '''%%{init: {"theme": "default", "themeVariables": {"fontSize": "24px"}}}%%
    flowchart TD
    Start((User Logs In)) --> Auth{Valid Auth?}
    Auth -- No --> Error[Error Message]
    Auth -- Yes --> Role{User Role?}
    Role -- Student --> S_Dash[Student Dashboard]
    Role -- Professor --> P_Dash[Professor Dashboard]
    Role -- Admin --> A_Dash[Admin Dashboard]
    S_Dash --> CheckEnrol{Enrolled?}
    CheckEnrol -- Yes --> S_Courses[View Courses]
    CheckEnrol -- No --> S_Reg[Register Courses]
    P_Dash --> P_Courses[Manage Courses]
    P_Courses --> P_Grades[Submit Grades]
    A_Dash --> A_Users[Manage Users]
    '''
}

for filename, code in diagrams.items():
    print(f'Downloading {filename}...')
    encoded = base64.b64encode(code.encode('utf-8')).decode('utf-8')
    url = f'https://mermaid.ink/img/{encoded}'
    out_path = os.path.join(r'e:\مشروع التخرج\EDU Nexus\diagrams', filename)
    try:
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req, timeout=30) as response:
            with open(out_path, 'wb') as out_file:
                out_file.write(response.read())
        print(f'Saved {filename}')
    except Exception as e:
        print(f'Error downloading {filename}: {e}')
