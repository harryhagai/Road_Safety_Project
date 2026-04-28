
 
NATIONAL INSTITUTE OF TRANSPORT
FACULTY OF INFORMATION TECHNOLOGY AND EDUCATION
DEPARTMEMT OF COMPUTING AND COMMUNICATION TECHNOLOGY

PROJECT PROPOSAL


Project Proposal Title: 	Development of Confidential Web Portal for Road Safety    Reporting  with Geospatial Mapping
Project Type:	SoftWare Project
Student Name: HAGAI HAROLD NGOBEY
Registration Number:NIT/BIT/2023/2185
Program: BIT
Level:	8
Supervisor Name: Mr.RODRICK MERO
 

Table of Contents
PROJECT BACKGROUND	4
PROBLEM STATEMENT	5
OBJECTIVES	6
Main Objective	6
Specific Objectives	6
LITERATURE REVIEW	7
Introduction	7
Review of Existing Systems	7
SIGNIFICANCE OF THE PROJECT	9
SCOPE OF THE PROJECT	10
Inclusions:	10
Exclusions:	10
SYSTEM DESIGN	11
System Architecture	11
Presentation Tier:	11
Application Tier:	11
Data Tier:	11
Use Case Diagram	11
Use Cases for Anonymous Reporters	12
Use Cases for Road Officers	12
Flowchart Diagram	14
Process Flow Description:	14
DATABASE DESIGN	16
Database Tables	16
8.2 Entity Relationship Diagram (ERD)	23
DATA FLOW DIAGRAM (DFD)	24
CONTEXT DIAGRAM (LEVEL 0 DFD)	24
LEVEL 1 DFD	25
Data Flow Descriptions	26
METHODOLOGY	27
Research Design	27
Data Collection Methods	27
System Development Methodology	28
Tools and Technologies	28
System Testing	28
Ethical Considerations	29
REFERENCES	30





















PROJECT BACKGROUND
Road traffic accidents continue to be a major public safety concern across the world, with developing nations being the most affected. According to the World Health Organization (2023), about 1.3 million deaths occur yearly due to road traffic crashes globally, with low- and middle-income nations contributing 93% of these deaths. In Tanzania, just like in other African countries, road traffic accidents are a major concern due to various reasons such as reckless driving, poor road conditions, and poor enforcement of traffic laws.
At present, road safety management in Tanzania follows a conventional reporting system in which citizens report road accidents via emergency phone calls and law enforcement agencies conduct physical patrols to detect violations. However, this system has a number of shortcomings such as a lack of accurate GPS location information in reports, which leads to slow response times; reporters cannot be anonymous, which leads to a low number of reports from citizens; and a lack of a database to analyze traffic patterns and identify accident-prone areas.
The digital transformation of public safety across the globe is picking up pace, with many countries adopting web-based and mobile technologies for incident reporting. These technologies use geospatial technologies for accurate location data collection. This project is aimed at developing a project proposal for the development of a Confidential Web Portal for Road Safety Reporting with Geospatial Mapping Integration. This web portal will allow citizens and commuters to report traffic violations in real-time without the need for account creation, thus allowing for anonymity. This will also allow transport authorities and road officers to access the reported data.












PROBLEM STATEMENT
However, there are critical challenges that affect the management of road safety in Tanzania, which prevent effective prevention and response to traffic violation. The challenges associated with the current manual reporting system include:
The current road safety management in Tanzania is primarily reactive, with a manual reporting approach. The reporting of traffic violation is done only after accidents occur. This approach is not effective for preventing traffic violation. The fear of retaliation or legal consequences for reporting traffic violation by the public also prevents effective reporting. The inability of the current phone reporting approach to provide accurate GPS coordinates for emergency response is also a challenge. The inability of the current approach to allow the submission of photos and videos also compromises the credibility of the report. The current approach also fails to effectively engage passengers and commuters as real-time observers of road safety.
Moreover, current digital reporting systems require users to register and have an account in order to be able to report violations. This acts as a deterrent to citizens from reporting violations, as they fear that they may be identified and end up being harassed or prosecuted in some way. Lack of anonymous reporting systems means that most traffic violations are not being reported, and dangerous driving habits are being allowed to continue unchecked.
These challenges and limitations continue to result in loss of lives, inefficient use of resources by the relevant authorities, and opportunities being missed in accident prevention. Therefore, there is a need for a secure, confidential, and geospatially integrated web portal that will enable citizens to report traffic violations anonymously without having to register for an account, and for transport authorities to be able to access and use this information for decision-making purposes.









OBJECTIVES
Main Objective
To design a secure, confidential, and web-based portal that allows citizens and commuters to report traffic violations in real-time without the need for account registration, using geospatial technology to promote accountability in the maintenance of road safety.
Specific Objectives
Objective 1: Design a web interface using Bootstrap that allows citizens to report traffic incidents using mobile devices without the need for account registration.
Objective 2: Integrate geospatial technology in capturing, visualizing, and analyzing the location of traffic violations in order to promote accountability in the maintenance of road safety.
Objective 3: Develop a secure web-based portal that allows transport authorities and road officers to login, manage, and analyze the reported road safety issues in order to promote informed decision-making.
Objective 4: Design a reporting mechanism that allows for preventive measures in road safety, based on the identification of risky locations and traffic violations.














LITERATURE REVIEW
Introduction
In this section, existing literature related to road safety reporting systems, geospatial mapping technologies, and secure web-based platforms will be discussed. This section will include the existing road safety reporting systems and their limitations, making the proposed idea of the Confidential Web Portal for Road Safety Reporting viable.
Review of Existing Systems
Kumar and Mishra (2022) proposed a mobile-based road incident reporting system with GPS technology. The proposed system enabled real-time accident reporting. The technologies used in the proposed system were Android Studio, Firebase, and Google Maps API. The proposed system enabled real-time accident reporting and GPS location tracking. However, the proposed system did not incorporate anonymity features, and the proposed system required registration, making it difficult for people to report incidents due to the risk of being identified.
Eweoya et al. (2025) designed a secure web-based system that enabled real-time reporting. The system ensured data security and reporting using Laravel and Bootstrap technologies. The study showed how secure web technologies could aid reporting, though most studies required users to log in and did not integrate geospatial visualization tools to aid real-time data analysis.
Another study by Diyaolu et al. (2024) presented a web-based system that enabled reporting, especially for public safety. The system improved data access for administrators, especially by automating manual reporting processes. The system was built using PHP, MySQL, and JavaScript programming languages. However, the study focused more on documenting incidents rather than preventive reporting, and users were required to register before reporting any violations.
Goodchild (2018) used Geographic Information System (GIS) technology for the mapping and analysis of the location of road accidents. While the use of GIS was instrumental in identifying the location of accidents, the focus was on the analysis of the location after the accident occurred, as opposed to crowdsourced reporting.
There are international best practices from countries like India, using the SaveLife App, the United Kingdom, using FixMyStreet, and the United States, using Waze Crowdsourced Alerts, which show the potential of crowdsourced data in the improvement of road safety, although these apps require user registration.




RESEARCH GAP
From the reviewed literature, some gaps have been identified as follows:
Most systems lack features that offer users anonymity and require users to register in order to use the system, which acts as a deterrent to reporting due to fear of reprisal. Few systems offer real-time mapping capabilities that are fully incorporated into the reporting process in a manner that ensures user anonymity. Few systems have been designed to offer accident reporting as opposed to preventive reporting against dangerous driving behaviors. There is a lack of systems that offer a combination of anonymous public reporting, administrative access, and geospatial analysis within a single system. There is a lack of leveraging passengers and commuters as real-time observers of road conditions due to registration issues. There is a need for more research and development in road safety reporting systems that are specifically tailored for use in Tanzania and that place emphasis on anonymous citizen participation.
The objective of this project is to bridge the existing gaps by designing a confidential and geospatially integrated web portal for citizens to report traffic violations confidentially without the need for registration, and also allow transport authorities and road officers to log in and utilize the reported data.














SIGNIFICANCE OF THE PROJECT
This project will be beneficial to various stakeholders in that:
Citizens and road users will have a secure and anonymous platform to report violations without the risk of being identified and possibly retaliated against, as no registration is required. This will give citizens an opportunity to actively participate in the improvement of road safety.
Transport authorities and road officers will have real-time information to aid in decision-making from a secure administrative interface. They will be able to identify and efficiently utilize information based on accurate location data.
Law enforcement agencies will have violation information to aid in enforcement and prosecution, and will be able to verify violations based on evidence provided.
The general public will benefit from safe roads as a result of the prevention and reduction of accidents arising from the early detection of dangerous practices and risky areas. 
Researchers and roadofficers will benefit as the system will serve as a foundation for other studies on citizen-based road safety systems, anonymous reporting systems, and geospatial technology applications in public safety. 
Government institutions will benefit as the system will contribute to the achievement of the nation's road safety and sustainable development goals and targets, as espoused by the Government of Tanzania in its efforts to reduce road traffic fatalities. 
System developers will benefit as the system will serve as a reference for developing similar anonymous reporting systems in other areas such as crime reporting systems, public service complaints, and environmental monitoring.










SCOPE OF THE PROJECT
This project includes the following:
Inclusions:
I.	Development of a web-based portal that can be accessed using desktop devices or mobile devices.
II.	Implementation of anonymous reporting of traffic violations without any need to register.
III.	Implementation of Google Maps API to capture locations.
IV.	Development of a secure admin dashboard with login capability for transport authorities/road officers.
V.	Development of reporting/notification tools to report status updates, identify hotspots, etc.
VI.	Testing/evaluation of the project in a region of your choice in Tanzania.

Exclusions:
I.	Development of mobile application software using native Android or native iOS platforms, as this is a web-based application.
II.	Integration with existing government infrastructure such as traffic lights, CCTV cameras, etc.
III.	Tracking of emergency response vehicles.
IV.	Payment/fine processing.
V.	Integration with social media sites.
VI.	Development of user accounts for reporters, as reporting is done anonymously.











SYSTEM DESIGN
System Architecture
The system will adopt a Three-Tier Architecture. The tiers are the presentation tier, application tier, and data tier.
Presentation Tier: This tier will cover all the aspects of user interaction with the system. For the anonymous reporters such as citizens and commuters, the anonymous reporting form with Google Maps integration for location selection, the system for displaying the violation location on the map for the anonymous reporters, and the interface for checking the report status with reference numbers are the major components. For the transport authorities and the road officers, the system for login for the transport authorities and the interface for displaying the report with the use of maps are the major components.
Application Tier: This tier handles all business logic and requests. Here, the Laravel framework is used, which handles authentication for road officers only, while reporters use the system anonymously. This tier handles report validation, report processing, geospatial data using Google Maps API, notifications, analytics, etc. Also, security protocols such as data encryption and access control for authorized personnel only are implemented.
Data Tier: This tier handles all data-related activities such as data storage and retrieval. Here, a MySQL database is used to store violation reports with coordinates, evidence files submitted by reporters, road officer accounts with password hashing, system logs, analytics data, etc.

Use Case Diagram
The system will involve two types of actors with different access privileges:
Anonymous Reporters (Citizens and Commuters), and Road Officers (Transport Authorities).
Actors Description
Anonymous Reporters – Citizens and Commuters who witness traffic violations and hazardous conditions on the road.
These users will interact with the system as anonymous reporters to encourage them to participate actively by reporting incidents.
Road Officers – The transport authorities and law enforcement agencies who will deal with incidents, carry out geospatial calculations, and oversee the regulation of road infrastructure.
These users will need to provide credentials to access the system.
Google Maps API – This will act as an external system actor.
This will form the basis of the geospatial calculations used to select the location, visualize incidents, and define boundaries.
Use Cases for Anonymous Reporters
i.	Report Traffic Violation – This will allow users to create detailed reports of incidents.
ii.	Select Location on Map – This will be a use case included in the system, with Google Maps used to locate the exact coordinates of the incident.
iii.	Upload Evidence: This allows users to upload images or videos as evidence for their reported incident.
iv.	View Incident Map: This allows users to see all reported incidents, helping other road users avoid these areas.
v.	Check Report Status: This enables users to track their individual reports using their unique reference number.

Use Cases for Road Officers
i.	Login to System: This allows authorized personnel to log into the system.
ii.	View Dashboard: This allows officers access to general statistics, recent reports, and general information.
iii.	Manage Reports: This enables officers to review and approve or reject reports from users.

iv.	Manage Geospatial Mapping: This allows officers to interact with the map and mark zones, boundaries, and other infrastructural features.

v.	Define Road Rules: This enables officers to define specific constraints for the map, such as speed limits or one-way roads.
vi.	Analyze Hotspots: This allows officers to use analytics to identify problem areas based on reported incident density.
vii.	Generate & Export Reports: This enables officers to export reports for planning and other purposes.
viii.	Send Notifications: This allows officers to inform users of their report status using their reference number.
ix.	Manage Officer Accounts: This allows officers with admin privileges to add or remove officers from the system.


THE USE CASE DIAGRAM


Diagram01.Use case diagram








Flowchart Diagram
The diagram shows the overall process flow of the Confidential Road Safety Reporting Portal, differentiating between actions of anonymous reporters and road officers.

Process Flow Description:
The process starts with an anonymous user accessing the web portal. From the homepage, the user selects "Report Incident." The system generates a report form with Google Maps integration. The user chooses the location of the incident on the map by clicking on the map or using GPS auto-detection. The user chooses the type of violation using a dropdown menu, adds a description of the incident, and may add evidence such as photos and videos. Upon completing the report, the system validates the report and assigns a unique reference number. It saves the report in the database with anonymous status and displays the reference number to the user. Users may also opt to view the public incident map or check the status of their previous report using their reference number.

From the administrative point of view, the road officer enters the secure login page, enters their details, and then is able to see a dashboard with summary statistics and a geospatial diagram showing all incidents reported. The officer is then able to filter incidents by date, location, type of violation, or status. The officer is then able to check each report’s details, evidence submitted, and then take action on it by verifying it, assigning it to field officers, marking it as resolved, or even rejecting it if found invalid. The system will then notify the anonymous reporter using their reference number that their report has been updated. The system will also update hot spot analytics based on verified reports.












FLOW CHART DIAGRAM




Diagram02.Flow chart diagram
DATABASE DESIGN
The database design refers to the description of the structure, storage, and relationships of the data in the system. The system utilizes MySQL as the relational database management system. The following are the defined tables and relationships:

 Database Tables
Table 1: officers
This table holds data about road officers and transport authorities who are authorized to use the admin dashboard.
Field Name	Data Type	Constraints	Description
officer_id	INT	PRIMARY KEY, AUTO_INCREMENT	Unique identifier for each officer
full_name	VARCHAR(100)	NOT NULL	Officer's full name
email	VARCHAR(100)	UNIQUE, NOT NULL	Login email address
password_hash	VARCHAR(255)	NOT NULL	Securely hashed password
role	ENUM('admin', 'officer')	NOT NULL, DEFAULT 'officer'	Access level privilege
created_at	TIMESTAMP	DEFAULT CURRENT_TIMESTAMP	Account creation date
last_login	TIMESTAMP	NULL	Last login timestamp











Table 2: reports
The table will hold all reports made by anonymous users like citizens and commuters.
Field Name	Data Type	Constraints	Description
report_id	INT	PRIMARY KEY, AUTO_INCREMENT	Unique report identifier
reference_no	VARCHAR(20)	UNIQUE, NOT NULL	Public reference number for tracking reports
violation_type	VARCHAR(50)	NOT NULL	Type of traffic violation reported
description	TEXT	NULL	Detailed description of the incident
latitude	DECIMAL(10,8)	NOT NULL	GPS latitude coordinate of incident location
longitude	DECIMAL(11,8)	NOT NULL	GPS longitude coordinate of incident location
location_name	VARCHAR(255)	NULL	Human-readable address or area name
evidence_path	VARCHAR(255)	NULL	File path to uploaded photo or video evidence
status	ENUM('pending', 'verified', 'resolved', 'rejected')	DEFAULT 'pending'	Current status of the report
officer_id	INT	FOREIGN KEY (officers.officer_id)	Officer who handled or verified the report
created_at	TIMESTAMP	DEFAULT CURRENT_TIMESTAMP	Date and time report was submitted
updated_at	TIMESTAMP	NULL	Date and time report was last updated
¬¬





Table 3: violation_types
The table will hold all types of violations that can be selected by anonymous reporters.
Field Name	Data Type	Constraints	Description
type_id	INT	PRIMARY KEY, AUTO_INCREMENT	Unique violation type identifier
name	VARCHAR(50)	NOT NULL	Name of the violation (e.g., Speeding)
description	TEXT	NULL	Detailed explanation of the violation
is_active	BOOLEAN	DEFAULT TRUE	Whether this violation type is available for selection

Table 4: hotspots
The table will hold analytics data on locations with higher risks.
Field Name	Data Type	Constraints	Description
hotspot_id	INT	PRIMARY KEY, AUTO_INCREMENT	Unique hotspot identifier
latitude	DECIMAL(10,8)	NOT NULL	Center latitude of the hotspot area
longitude	DECIMAL(11,8)	NOT NULL	Center longitude of the hotspot area
radius	INT	DEFAULT 500	Radius in meters defining the hotspot boundary
frequency	INT	DEFAULT 1	Number of reports recorded in this hotspot area
severity	ENUM('low', 'medium', 'high')	NOT NULL	Risk level based on report types and frequency
last_updated	TIMESTAMP	DEFAULT CURRENT_TIMESTAMP	Date and time hotspot data was last calculated







Table 5: notifications
The table will hold notifications sent to anonymous reporters.
Field Name	Data Type	Constraints	Description
notification_id	INT	PRIMARY KEY, AUTO_INCREMENT	Unique notification identifier
reference_no	VARCHAR(20)	NOT NULL	Report reference number associated with this notification
message	TEXT	NOT NULL	Notification content or message
type	VARCHAR(50)	NOT NULL	Type of notification (e.g., status_update, resolved)
sent_at	TIMESTAMP	DEFAULT CURRENT_TIMESTAMP	Date and time notification was sent

Table 6: evidence_files
The table will hold information about evidence files uploaded with reports.
Field Name	Data Type	Constraints	Description
evidence_id	INT	PRIMARY KEY, AUTO_INCREMENT	Unique evidence identifier
report_id	INT	FOREIGN KEY (reports.report_id)
Report associated with this evidence
file_name	VARCHAR(255)	NOT NULL	Original name of the uploaded file
file_path	VARCHAR(255)	NOT NULL	Server path where the file is stored
file_type	VARCHAR(50)	NOT NULL	File type (image/jpeg, image/png, video/mp4, etc.)
file_size	INT	NOT NULL	File size in bytes
uploaded_at	TIMESTAMP	DEFAULT CURRENT_TIMESTAMP	Date and time file was uploaded





Table7: road_rules
This table holds the road rules or mapping constraints defined by road officers at various locations. The rules are helpful in identifying violations as well as mapping high-risk areas.
Field Name	Data Type	Constraints	Description
rule_id	INT	PRIMARY KEY, AUTO_INCREMENT	Unique rule identifier
rule_name	VARCHAR(100)	NOT NULL	Name of the rule (e.g., Speed Limit - Morogoro Road)
rule_type	ENUM('speed_limit', 'no_overtaking',..)	NOT NULL	Type of road rule defined
latitude_start	DECIMAL(10,8)	NOT NULL	Starting latitude coordinate of the rule area
longitude_start	DECIMAL(11,8)	NOT NULL	Starting longitude coordinate of the rule area
latitude_end	DECIMAL(10,8)	NULL	Ending latitude coordinate (for zones or segments)
longitude_end	DECIMAL(11,8)	NULL	Ending longitude coordinate (for zones or segments)
location_name	VARCHAR(255)	NOT NULL	Human-readable location 
rule_value	VARCHAR(50)	NOT NULL	Value of the rule (e.g., "80" for speed limit in km/h,..)
description	TEXT	NULL	Additional details about the rule
effective_from	DATE	NOT NULL	Date when the rule becomes effective
effective_to	DATE	NULL	Date when the rule expires (NULL if permanent)
is_active	BOOLEAN	DEFAULT TRUE	Whether the rule is currently active
created_by	INT	FOREIGN KEY (officers.officer_id)	Officer who created or defined the rule
Time_stsmps	TIMESTAMP	DEFAULT CURRENT_TIMESTAMP	Date and time rule was created
updated_at	TIMESTAMP	NULL	Date and time rule was last updated

Table 8: road_segments
This table holds the road segments or zones that have specific mapping boundaries defined. This helps in organizing road rules based on specific geographical areas.
Field Name	Data Type	Constraints	Description
segment_id	INT	PRIMARY KEY, AUTO_INCREMENT	Unique road segment identifier
segment_name	VARCHAR(100)	NOT NULL	Name of the road segment (e.g., "Morogoro Road - Posta to Kariakoo")
segment_type	ENUM('highway', 'urban', 'rural', 'residential', 'school_zone', 'construction')	NOT NULL	Type of road segment
boundary_coordinates	TEXT	NOT NULL	Polygon or polyline coordinates defining the segment boundaries (GeoJSON format)
length_km	DECIMAL(10,2)	NULL	Length of the road segment in kilometers
description	TEXT	NULL	Additional information about the road segment
created_by	INT	FOREIGN KEY (officers.officer_id)	Officer who created the segment
created_at	TIMESTAMP	DEFAULT CURRENT_TIMESTAMP	Date and time segment was created
updated_at	TIMESTAMP	NULL	Date and time segment was last updated




Table 9: rule_violations
This table maps reported violations to specific defined road rules. This helps in automatically flagging reports based on specific road rules.
Field Name	Data Type	Constraints	Description
violation_link_id	INT	PRIMARY KEY, AUTO_INCREMENT	Unique link identifier
report_id	INT	FOREIGN KEY (reports.report_id)
Report associated with this rule violation
rule_id	INT	FOREIGN KEY (road_rules.rule_id)	Road rule that was violated
matched_automatically	BOOLEAN	DEFAULT FALSE	Whether the system automatically matched the report to this rule
confidence_score	DECIMAL(5,2)	NULL	Confidence score for automatic matching (0-100)
verified_by	INT	FOREIGN KEY (officers.officer_id)	Officer who verified this rule violation
verified_at	TIMESTAMP	NULL	Date and time verification was done












8.2 Entity Relationship Diagram (ERD)
The above ERD shows the relationships between entities in the database
Diagram03.Entity relationship diagram
DATA FLOW DIAGRAM (DFD)
The Data Flow Diagram shows the flow of data through the system. It shows the external entities, processes, data stores, and data flows. It also shows the flow of information from the anonymous reporters and the road officers to the system components.

CONTEXT DIAGRAM (LEVEL 0 DFD)
The context diagram shows the system as a single process with external entities interacting with it. This is the highest level of the system.


Diagram04.Context diagram level 0 data flow diagram






LEVEL 1 DFD
The level 1 DFD will identify the major processes within a system and show data flow between these processes.


Diagram05.Level 1 data flow diagram
Data Flow Descriptions
Flow No.	From	To	Data Description
1	Citizen/Commuter	Process 1.0	Report submission: violation type, description, location, evidence
2	Citizen/Commuter	Process 3.0	Status check request using reference number
3	Process 8.0	Citizen/Commuter	Status update: report verification, resolution notification
4	Process 2.0	Google Maps API	Location coordinates for reverse geocoding
5	Google Maps API	Process 2.0	Address data, map visualization
6	Process 5.0	Citizen/Commuter	Report status notifications (via Process 8.0)
7	Road Officer	Process 4.0	Login credentials
8	Road Officer	Process 5.0	Report management actions (verify, resolve, reject)
9	Road Officer	Process 6.0	Road rules definition (speed limits, zones, boundaries)
10	Road Officer	Process 7.0	Analytics requests (hotspots, reports, statistics)
11	Process 7.0	Road Officer	Analytics results, hotspot maps, report summaries
12	Process 3.0	DS3	Save validated report to database
13	Process 5.0	DS3	Update report status
14	Process 6.0	DS5	Save road rules and mapping boundaries
15	Process 7.0	DS6	Update hotspot analytics
16	Process 8.0	DS7	Store notification records











METHODOLOGY
Research Design
For the project, the research design to be used is the Design Science Research Methodology (DSRM). DSRM is a research methodology that aims to create and evaluate a practical solution to the problem identified. This is the appropriate methodology to use because the project aims to create a software artifact (web portal) to solve the problem.
The research design to be used is divided into five phases:
Phase	Activity
Phase 1	Problem identification through literature review and stakeholder interviews
Phase 2	Definition of system objectives and requirements
Phase 3	Design and development of the web portal
Phase 4	Demonstration and testing with sample users
Phase 5	Evaluation and documentation of findings

Data Collection Methods
The following data collection methods will be used:
Method	Purpose	Target Participants
Interviews	Gather requirements from transport authorities	5-10 road officers and SUMATRA officials
Questionnaires	Assess citizen willingness to report anonymously	30-50 commuters and general public
Document Review	Analyze existing reporting procedures	Traffic reports, police records
System Testing	Evaluate system functionality and usability	5 anonymous reporters, 3 road officers






System Development Methodology
The system development will be carried out using Agile Methodology  . The development of the system will be completed within 8 sprints over a period of 16 weeks:
Sprint	Duration	Activities
Sprint 1	Week 1-2	Requirements gathering and analysis
Sprint 2	Week 3-4	System design (architecture, DFD, database)
Sprint 3	Week 5-6	Frontend development (Bootstrap interface)
Sprint 4	Week 7-9	Backend development (Laravel, database)
Sprint 5	Week 10-11	Geospatial integration (Google Maps API)
Sprint 6	Week 12-13	Admin dashboard and road rules management
Sprint 7	Week 14-15	Testing and debugging
Sprint 8	Week 16	Documentation and final report

Tools and Technologies
The tools and technologies used will be:
Component	Technology
Frontend	HTML5, CSS3, Bootstrap 5, JavaScript
Backend	PHP, Laravel Framework
Database	MySQL
Geospatial	Google Maps API
Version Control	Git, GitHub
Testing	PHPUnit, BrowserStack
Hosting	Cloud Platform (AWS/Heroku)

System Testing
The system will be tested at three levels:
Testing Level	Purpose
Unit Testing	Test individual components (report submission, validation, authentication)
Integration Testing	Test interaction between frontend, backend, and Google Maps API
User Acceptance Testing	Test with 5 anonymous reporters and 3 road officers to evaluate usability and functionality

Ethical Considerations
I.	Informed consent will be obtained from the participants of the interview and questionnaire study
II.	Reporters remain anonymous; no personal details will be collected
III.	The data will be used for roadofficer purposes only; it will be stored safely





















REFERENCES
Diyaolu, A. M., Abodunrin, O. B., Adedamola, A. A., Ogunode, R. S., & Omoloba, K. S. (2024). Development of web-based reporting systems for public safety applications. International Journal of Innovative Science and Research Technology, 9(3), 112–118.
Eweoya, I., Awoniyi, A., Adeniyi, O., Okesola, K., Udosen, A., & Amusa, A. (2025). Secure web-based systems for real-time incident reporting. British Journal of Computer, Networking and Information Technology, 7(1), 45–53.
Goodchild, M. F. (2018). Geographic information systems and spatial analysis in transportation safety. Journal of Transport Geography, 67, 1–10.
Kumar, S., & Mishra, D. (2022). Mobile-based road incident reporting systems using GPS technology. International Journal of Computer Applications, 174(15), 21–27.
Mwangi, J., Ochieng, D., & Akinyi, P. (2021). Web-based road incident reporting system for Nairobi County. East African Journal of Information Technology, 4(1), 45–58.
Ogunleye, T., & Adebayo, S. (2023). Geospatial analysis of road traffic accidents in Lagos metropolis. Nigerian Journal of Transportation Studies, 12(2), 78–92.
World Health Organization. (2023). Global status report on road safety 202
