# FluentAI - AI-Powered Language Learning Platform

<div align="center">

![Next.js](https://img.shields.io/badge/Next.js-16.1.3-black?style=for-the-badge&logo=next.js)
![React](https://img.shields.io/badge/React-19.2.3-61DAFB?style=for-the-badge&logo=react)
![TypeScript](https://img.shields.io/badge/TypeScript-5.0-3178C6?style=for-the-badge&logo=typescript)
![Tailwind CSS](https://img.shields.io/badge/Tailwind-4.0-06B6D4?style=for-the-badge&logo=tailwindcss)
![Google AI](https://img.shields.io/badge/Gemini_AI-Vertex_AI-4285F4?style=for-the-badge&logo=google)

**An adaptive, AI-powered English learning platform with personalized lessons, real-time feedback, and CEFR-aligned progression.**

[Features](#-features) • [Tech Stack](#-tech-stack) • [Getting Started](#-getting-started) • [Project Structure](#-project-structure) • [API Routes](#-api-routes)

</div>

---

## 🎯 Features

### 🧠 AI-Powered Learning
- **Adaptive Question Generation** - AI generates questions tailored to your CEFR level (A1-C2)
- **Real-time Feedback** - Instant explanations for every answer
- **Personalized Study Plans** - AI creates custom learning paths based on your goals

### 📊 Assessment & Progress
- **Placement Test** - Comprehensive 30-question test to determine your starting level
- **Level Validation** - Quick 10-question tests to confirm your CEFR level
- **Progress Tracking** - Detailed analytics with charts and insights
- **Level-Up System** - Automatic progression when you're ready

### 📚 Learning Modules

| Module | Description |
|--------|-------------|
| **Practice** | AI-generated MCQ and fill-in-the-blank exercises |
| **Vocabulary** | Context-based word learning with spaced repetition |
| **Grammar** | Structured grammar lessons with examples |
| **Reading** | AI-generated stories adapted to your level |
| **Listening** | Dictation exercises with text-to-speech |
| **Writing** | AI-graded writing assignments with detailed feedback |
| **Speaking** | Pronunciation practice with scenario-based dialogues |
| **AI Tutor** | 24/7 conversational AI tutor for questions |

### 🎨 User Experience
- **Modern Dark UI** - Beautiful, eye-friendly dark theme
- **Responsive Design** - Works on desktop, tablet, and mobile
- **Gamification** - XP points, streaks, and achievements
- **Offline Progress** - LocalStorage-based progress saving

---

## 🛠 Tech Stack

### Frontend
- **Next.js 16.1.3** - React framework with App Router
- **React 19.2.3** - UI library with React Compiler
- **TypeScript 5** - Type-safe development
- **Tailwind CSS 4** - Utility-first styling
- **Recharts** - Data visualization for progress charts

### Backend
- **Next.js API Routes** - Serverless API endpoints
- **Google Vertex AI** - Gemini 2.0 Flash model for AI generation
- **Google Cloud TTS** - Text-to-speech for listening exercises
- **Zod** - Runtime schema validation for AI responses

### Architecture
```
Browser → Client Components → API Routes → Vertex AI → Zod Validation → UI
```

---

## 🚀 Getting Started

### Prerequisites
- Node.js 18+ 
- npm or yarn
- Google Cloud account with Vertex AI API enabled

### 1. Clone the Repository
```bash
git clone https://github.com/YOUR_USERNAME/adaptive-duo.git
cd adaptive-duo
```

### 2. Install Dependencies
```bash
npm install
```

### 3. Configure Environment Variables
Create a `.env.local` file in the root directory:

```env
# Google Cloud Vertex AI Configuration
GOOGLE_CLOUD_PROJECT_ID=your-project-id
GOOGLE_CLOUD_CLIENT_EMAIL=your-service-account@your-project.iam.gserviceaccount.com
GOOGLE_CLOUD_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
GOOGLE_CLOUD_LOCATION=us-central1
```

#### Getting Google Cloud Credentials:
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable **Vertex AI API**
4. Go to **IAM & Admin** → **Service Accounts**
5. Create a service account with **Vertex AI User** role
6. Generate a JSON key and extract the values

### 4. Run Development Server
```bash
npm run dev
```

Open [http://localhost:3000](http://localhost:3000) in your browser.

### 5. Build for Production
```bash
npm run build
npm start
```

---

## 📁 Project Structure

```
adaptive-duo/
├── src/
│   ├── app/                    # Next.js App Router pages
│   │   ├── api/                # API routes
│   │   │   ├── practice/       # Practice question generation
│   │   │   ├── placement/      # Placement test
│   │   │   ├── cefr-test/      # Level validation
│   │   │   ├── vocab/          # Vocabulary exercises
│   │   │   ├── tutor-chat/     # AI tutor conversations
│   │   │   ├── grade-writing/  # Writing assessment
│   │   │   ├── dictation/      # Listening exercises
│   │   │   ├── pronunciation/  # Speaking exercises
│   │   │   ├── story/          # Reading stories
│   │   │   └── ...
│   │   ├── dashboard/          # Main dashboard
│   │   ├── practice/           # Practice session
│   │   ├── placement/          # Placement test
│   │   ├── vocab/              # Vocabulary learning
│   │   ├── ai-tutor/           # AI tutor chat
│   │   ├── progress/           # Progress analytics
│   │   ├── welcome/            # Onboarding flow
│   │   │   ├── cefr-test/      # Level validation
│   │   │   ├── cefr/           # CEFR selection
│   │   │   └── ...
│   │   └── ...
│   └── lib/
│       ├── vertex-ai.ts        # Google Vertex AI integration
│       ├── store.ts            # LocalStorage state management
│       └── utils.ts            # Utility functions
├── public/                     # Static assets
├── .env.local                  # Environment variables (not in git)
├── package.json
├── tailwind.config.ts
└── tsconfig.json
```

---

## 🔌 API Routes

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/practice` | POST | Generate practice questions |
| `/api/placement` | POST | Generate placement test |
| `/api/placement/evaluate` | POST | Evaluate placement results |
| `/api/cefr-test` | POST | Generate level validation test |
| `/api/vocab` | POST | Generate vocabulary exercises |
| `/api/tutor-chat` | POST | AI tutor conversation |
| `/api/grade-writing` | POST | Grade writing submissions |
| `/api/dictation` | POST | Generate dictation exercises |
| `/api/dictation/check` | POST | Check dictation answers |
| `/api/pronunciation` | POST | Generate pronunciation exercises |
| `/api/story` | POST | Generate reading stories |
| `/api/study-plan` | POST | Generate personalized study plan |
| `/api/progress-insight` | POST | Get AI progress insights |
| `/api/levelup` | POST | Generate level-up test |
| `/api/levelup/analyze` | POST | Analyze level-up results |

---

## 🎓 CEFR Levels

The platform follows the **Common European Framework of Reference for Languages**:

| Level | Description | Skills |
|-------|-------------|--------|
| **A1** | Beginner | Basic phrases, introductions |
| **A2** | Elementary | Simple conversations, daily routines |
| **B1** | Intermediate | Main points, travel, experiences |
| **B2** | Upper Intermediate | Complex texts, fluent interaction |
| **C1** | Advanced | Demanding texts, implicit meaning |
| **C2** | Proficiency | Near-native understanding |

---

## 🔒 Security Notes

- **Never commit `.env.local`** - Contains sensitive API keys
- The `.gitignore` already excludes environment files
- Use environment variables in production (Vercel, etc.)

---

## 📦 Deployment

### Vercel (Recommended)
1. Push to GitHub
2. Import project in [Vercel](https://vercel.com)
3. Add environment variables in Vercel dashboard
4. Deploy!

### Other Platforms
The app can be deployed on any platform supporting Next.js:
- AWS Amplify
- Google Cloud Run
- Railway
- Render

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- [Google Vertex AI](https://cloud.google.com/vertex-ai) - AI/ML platform
- [Next.js](https://nextjs.org/) - React framework
- [Tailwind CSS](https://tailwindcss.com/) - CSS framework
- [Zod](https://zod.dev/) - TypeScript schema validation

---

<div align="center">

**Built with ❤️ for language learners worldwide**

</div>
