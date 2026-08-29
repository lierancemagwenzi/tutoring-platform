<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate of Completion</title>
    <style>
        @page {
            size: landscape;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1f2933;
        }

        .frame {
            width: 100%;
            height: 100%;
            padding: 24px;
            box-sizing: border-box;
        }

        .border {
            border: 3px solid #111827;
            padding: 48px 64px;
            text-align: center;
        }

        .brand {
            font-size: 16px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #b45309;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .title {
            font-size: 34px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin: 0 0 32px;
            color: #111827;
        }

        .preamble {
            font-size: 13px;
            color: #4b5563;
            margin-bottom: 4px;
        }

        .student-name {
            font-size: 32px;
            font-weight: bold;
            margin: 12px 0 24px;
            color: #111827;
            border-bottom: 1px solid #d1d5db;
            display: inline-block;
            padding: 0 24px 8px;
        }

        .course-line {
            font-size: 14px;
            color: #374151;
            margin-bottom: 4px;
        }

        .course-title {
            font-size: 22px;
            font-weight: bold;
            color: #111827;
            margin: 4px 0 32px;
        }

        .meta-table {
            width: 100%;
            margin-top: 40px;
        }

        .meta-table td {
            width: 33%;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
        }

        .meta-value {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            display: block;
            margin-top: 4px;
        }

        .footer {
            margin-top: 32px;
            font-size: 9px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="frame">
        <div class="border">
            <div class="brand">ItsLearnable</div>
            <div class="title">Certificate of Completion</div>

            <div class="preamble">This certifies that</div>
            <div class="student-name">{{ $certificate->student_name }}</div>

            <div class="course-line">has successfully completed the course</div>
            <div class="course-title">{{ $certificate->course_title }}</div>

            <table class="meta-table">
                <tr>
                    <td>
                        Instructor
                        <span class="meta-value">{{ $certificate->tutor_name }}</span>
                    </td>
                    <td>
                        Completion Date
                        <span class="meta-value">{{ $certificate->issued_at->format('F j, Y') }}</span>
                    </td>
                    <td>
                        Certificate No.
                        <span class="meta-value">{{ $certificate->certificate_number }}</span>
                    </td>
                </tr>
            </table>

            <div class="footer">
                Verification ID: {{ $certificate->verification_uuid }}
            </div>
        </div>
    </div>
</body>
</html>
