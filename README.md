```mermaid
flowchart TD
    %% Student Enrollment Process
    A[Student Enrolment] --> B[Paystack Payment]
    B --> C[Fill Application Form]
    C --> D[Save to Database]

    %% Entrance Examination Flow
    D --> E{Entrance Exam?}
    E -->|CBT| F[Take Online CBT Exam]
    E -->|In School| G[Manual Exam Entry]
    F --> H[Evaluate CBT Result]
    G --> H

    %% Registration Process
    H --> I{Passed Exam?}
    I -->|Yes| J[Full Registration]
    J --> K[Create Student Portal]
    H -->|No| AD[Reject Registration]

    %% Student Portal Activities
    K --> L[Login to Portal]
    L --> M[Pay School Fees]
    M -->|Online Payment| N[Paystack Payment → Auto Update]
    M -->|In School Payment| O[Admin Manual Update]
    L --> P[View CBT Results]
    L --> Q[Receive Notifications & Messages]

    %% Admin Section
    R[Admin Dashboard]
    R --> S[Manage Student Records]
    R --> T[Create/Manage Teacher Accounts]
    R --> U[Create/Manage Class Teachers]
    R --> V[Send Notifications / Messages]
    R --> W[Update School Fees Records]

    %% Teacher Portal
    X[Teacher Portal]
    T --> X
    X --> Y[Manage Exam Questions]
    X --> Z[View Student Performance]

    %% Class Teacher Portal
    AA[Class Teacher Portal]
    U --> AA
    AA --> AB[View Class Student Data]
    AA --> AC[Update Student Records]

    %% Styling
    classDef admin fill:#f9f,stroke:#333,stroke-width:2px
    classDef teacher fill:#cfc,stroke:#333,stroke-width:2px
    classDef classTeacher fill:#ccf,stroke:#333,stroke-width:2px
    classDef portal fill:#bfb,stroke:#333,stroke-width:2px
    classDef payment fill:#fdd,stroke:#333,stroke-width:2px

    class R admin
    class X teacher
    class AA classTeacher
    class K portal
    class M payment