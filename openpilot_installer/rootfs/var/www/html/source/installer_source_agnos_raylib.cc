// Standalone openpilot installer for AGNOS with Raylib
// Compatible with openpilot v0.10.0+ (post Qt-to-Raylib migration)
// This file is designed to be compiled standalone and then binary-patched
// with custom GitHub URL and branch values.
//
// Build for comma device (aarch64):
//   aarch64-linux-gnu-g++ -std=c++17 -O2 -static \
//     -I/path/to/raylib/include \
//     -o installer_openpilot_agnos_raylib \
//     installer_source_agnos_raylib.cc \
//     -L/path/to/raylib/lib -lraylib -lGLESv2 -lEGL -lm -lpthread -ldl

#include <time.h>
#include <unistd.h>
#include <cstdlib>
#include <cstdarg>
#include <fstream>
#include <map>
#include <string>
#include <thread>
#include <atomic>
#include <mutex>
#include <cstring>
#include <sys/stat.h>
#include <sys/wait.h>
#include <fcntl.h>
#include <iostream>
#include <sstream>
#include <vector>
#include <algorithm>
#include <cmath>
#include <chrono>

#include <raylib.h>

// Placeholder strings for binary patching
// The placeholders will be replaced by the PHP script at runtime
// Format: placeholder string followed by '?' and padding spaces

// GIT_URL placeholder: "https://github.com/" + placeholder for username/repo.git
// Placeholder is 53 chars (enough for username(39) + "/" + repo + ".git")
#define GIT_URL_PLACEHOLDER "https://github.com/27182818284590452353602874713526624977572470936999595"

// BRANCH placeholder: max 255 chars for branch name
#define BRANCH_PLACEHOLDER "161803398874989484820458683436563811772030917980576286213544862270526046281890244970720720418939113748475408807538689175212663386222353693179318006076672635443338908659593958290563832266131992829026788067520876689250171169620703222104321626954862629631361"

// Loading message placeholder: max 39 chars
#define LOADING_MSG_PLACEHOLDER "314159265358979323846264338327950288419"

#define CONTINUE_PATH "/data/continue.sh"
#define CACHE_PATH "/usr/comma/openpilot"
#define INSTALL_PATH "/data/openpilot"
#define TMP_INSTALL_PATH "/data/tmppilot"

// Continue script content - this is what launches openpilot after installation
const char* CONTINUE_SCRIPT =
    "#!/usr/bin/env bash\n"
    "\n"
    "cd /data/openpilot\n"
    "exec ./launch_openpilot.sh\n";

// Helper function to get string up to null terminator (for patched placeholders)
std::string get_patched_str(const char* s) {
    return std::string(s);
}

// Global strings initialized from placeholders
const std::string GIT_URL = get_patched_str(GIT_URL_PLACEHOLDER);
const std::string BRANCH = get_patched_str(BRANCH_PLACEHOLDER);
const std::string LOADING_MSG = get_patched_str(LOADING_MSG_PLACEHOLDER);

class RaylibInstaller {
private:
    std::atomic<int> progress{0};
    std::atomic<bool> installing{true};
    std::atomic<bool> installation_complete{false};
    std::string title_text;
    std::string status_text = "0%";
    std::mutex text_mutex;

    // UI constants - comma device screen is 2160x1080
    static const int WINDOW_WIDTH = 2160;
    static const int WINDOW_HEIGHT = 1080;
    static const int MARGIN_LEFT = 150;
    static const int MARGIN_TOP = 290;
    static const int PROGRESS_BAR_WIDTH = 1860; // WINDOW_WIDTH - 2 * MARGIN_LEFT
    static const int PROGRESS_BAR_HEIGHT = 72;
    static const int TITLE_FONT_SIZE = 110;
    static const int PERCENT_FONT_SIZE = 85;

    // Colors
    Color backgroundColor = BLACK;
    Color textColor = WHITE;
    Color progressBarBg = {41, 41, 41, 255}; // #292929
    Color progressBarFill = {70, 91, 234, 255}; // comma blue

    // Process handling
    std::thread install_thread;

public:
    RaylibInstaller() {
        title_text = "Installing " + LOADING_MSG + "...";
    }

    ~RaylibInstaller() {
        if (install_thread.joinable()) {
            install_thread.join();
        }
    }

    void run() {
        InitWindow(WINDOW_WIDTH, WINDOW_HEIGHT, "Installer");
        SetTargetFPS(60);

        // Check if already installed (continue.sh exists)
        if (file_exists(CONTINUE_PATH)) {
            finishInstall();
            CloseWindow();
            return;
        }

        // Start installation in background thread
        install_thread = std::thread(&RaylibInstaller::doInstall, this);

        // Main render loop
        while (!WindowShouldClose() && installing) {
            BeginDrawing();
            ClearBackground(backgroundColor);
            drawUI();
            EndDrawing();
        }

        CloseWindow();
    }

private:
    bool file_exists(const char* path) {
        struct stat buffer;
        return (stat(path, &buffer) == 0);
    }

    bool directory_exists(const char* path) {
        struct stat info;
        return stat(path, &info) == 0 && S_ISDIR(info.st_mode);
    }

    bool time_valid() {
        time_t rawtime;
        time(&rawtime);
        struct tm* sys_time = gmtime(&rawtime);
        return (1900 + sys_time->tm_year) >= 2020;
    }

    void run_command(const char* cmd) {
        int err = std::system(cmd);
        if (err != 0) {
            std::cerr << "Command failed: " << cmd << " (exit code: " << err << ")" << std::endl;
        }
    }

    std::string string_format(const char* format, ...) {
        char buffer[1024];
        va_list args;
        va_start(args, format);
        vsnprintf(buffer, sizeof(buffer), format, args);
        va_end(args);
        return std::string(buffer);
    }

    void drawUI() {
        std::lock_guard<std::mutex> lock(text_mutex);

        // Draw title
        DrawText(title_text.c_str(), MARGIN_LEFT, MARGIN_TOP, TITLE_FONT_SIZE, textColor);

        // Draw progress bar
        int progressBarY = MARGIN_TOP + 280;
        drawProgressBar(MARGIN_LEFT, progressBarY);

        // Draw percentage
        int percentY = progressBarY + PROGRESS_BAR_HEIGHT + 30;
        DrawText(status_text.c_str(), MARGIN_LEFT, percentY, PERCENT_FONT_SIZE, textColor);
    }

    void drawProgressBar(int x, int y) {
        // Background
        DrawRectangle(x, y, PROGRESS_BAR_WIDTH, PROGRESS_BAR_HEIGHT, progressBarBg);

        // Progress fill
        int currentProgress = progress.load();
        if (currentProgress > 0) {
            int fillWidth = (PROGRESS_BAR_WIDTH * currentProgress) / 100;
            DrawRectangle(x, y, fillWidth, PROGRESS_BAR_HEIGHT, progressBarFill);
        }
    }

    void updateProgress(int percent) {
        if (percent < 0) percent = 0;
        if (percent > 100) percent = 100;
        progress = percent;
        std::lock_guard<std::mutex> lock(text_mutex);
        status_text = std::to_string(percent) + "%";
    }

    void updateTitle(const std::string& new_title) {
        std::lock_guard<std::mutex> lock(text_mutex);
        title_text = new_title;
    }

    void finishInstall() {
        updateTitle("Finishing install...");
        updateProgress(100);

        // Wait 60 seconds for the installed software's UI to take over
        for (int i = 0; i < 600 && !WindowShouldClose(); i++) {
            BeginDrawing();
            ClearBackground(backgroundColor);

            const char* msg = "Finishing install...";
            int textWidth = MeasureText(msg, TITLE_FONT_SIZE);
            DrawText(msg, (WINDOW_WIDTH - textWidth) / 2, (WINDOW_HEIGHT - TITLE_FONT_SIZE) / 2,
                    TITLE_FONT_SIZE, textColor);

            EndDrawing();
            std::this_thread::sleep_for(std::chrono::milliseconds(100));
        }

        installing = false;
    }

    void doInstall() {
        // Wait for valid time
        while (!time_valid()) {
            usleep(500 * 1000);
            std::cout << "Waiting for valid time" << std::endl;
        }

        // Cleanup previous install attempts
        run_command("rm -rf " TMP_INSTALL_PATH);

        // Do the install
        if (directory_exists(CACHE_PATH)) {
            cachedFetch();
        } else {
            freshClone();
        }
    }

    void freshClone() {
        std::cout << "Doing fresh clone from " << GIT_URL << " branch " << BRANCH << std::endl;

        std::string cmd = "git clone --progress " + GIT_URL + " -b " + BRANCH +
                         " --depth=1 --recurse-submodules " + std::string(TMP_INSTALL_PATH) + " 2>&1";

        executeGitCommand(cmd);
    }

    void cachedFetch() {
        std::cout << "Fetching with cache" << std::endl;

        run_command("cp -rp " CACHE_PATH " " TMP_INSTALL_PATH);

        std::string set_branch_cmd = "cd " + std::string(TMP_INSTALL_PATH) +
                                     " && git remote set-branches --add origin " + BRANCH;
        run_command(set_branch_cmd.c_str());
        updateProgress(10);

        std::string cmd = "cd " + std::string(TMP_INSTALL_PATH) +
                         " && git fetch --progress origin " + BRANCH + " 2>&1";
        executeGitCommand(cmd);
    }

    void executeGitCommand(const std::string& cmd) {
        FILE* fp = popen(cmd.c_str(), "r");
        if (fp == nullptr) {
            perror("popen");
            updateTitle("Installation failed!");
            std::this_thread::sleep_for(std::chrono::seconds(10));
            installing = false;
            return;
        }

        char buffer[1024];
        while (fgets(buffer, sizeof(buffer), fp) != nullptr) {
            parseGitProgress(std::string(buffer));
        }

        int status = pclose(fp);
        if (status != 0) {
            std::cerr << "Git command failed with status: " << status << std::endl;
            updateTitle("Installation failed!");
            std::this_thread::sleep_for(std::chrono::seconds(10));
            installing = false;
            return;
        }

        cloneFinished();
    }

    void parseGitProgress(const std::string& line) {
        // Parse git progress output
        struct Stage {
            std::string prefix;
            int weight;
        };

        std::vector<Stage> stages = {
            {"Receiving objects: ", 91},
            {"Resolving deltas: ", 2},
            {"Updating files: ", 7},
        };

        int base = 0;
        for (const auto& stage : stages) {
            if (line.find(stage.prefix) != std::string::npos) {
                size_t percent_pos = line.find("%");
                if (percent_pos != std::string::npos && percent_pos >= 3) {
                    try {
                        int percent = std::stoi(line.substr(percent_pos - 3, 3));
                        int p = base + (int)(percent / 100.0f * stage.weight);
                        updateProgress(p);
                    } catch (const std::exception& e) {
                        // Ignore parsing errors
                    }
                }
                break;
            }
            base += stage.weight;
        }
    }

    void cloneFinished() {
        std::cout << "Git operation finished successfully" << std::endl;

        updateTitle("Finalizing installation...");
        updateProgress(100);

        // Ensure correct branch is checked out
        std::string checkout_cmd = "cd " + std::string(TMP_INSTALL_PATH) + " && git checkout " + BRANCH;
        run_command(checkout_cmd.c_str());

        std::string reset_cmd = "cd " + std::string(TMP_INSTALL_PATH) + " && git reset --hard origin/" + BRANCH;
        run_command(reset_cmd.c_str());

        run_command("cd " TMP_INSTALL_PATH " && git submodule update --init");

        // Remove old installation and cache marker
        run_command("rm -f /data/.openpilot_cache");
        run_command("rm -rf " INSTALL_PATH);

        // Move into place
        run_command("mv " TMP_INSTALL_PATH " " INSTALL_PATH);

        // Write continue.sh
        std::ofstream continue_script("/data/continue.sh.new");
        if (continue_script.is_open()) {
            continue_script << CONTINUE_SCRIPT;
            continue_script.close();
            run_command("chmod +x /data/continue.sh.new");
            run_command("mv /data/continue.sh.new " CONTINUE_PATH);
        } else {
            std::cerr << "Failed to create continue.sh" << std::endl;
        }

        // Finish installation
        finishInstall();
    }
};

int main(int argc __attribute__((unused)), char *argv[] __attribute__((unused))) {
    RaylibInstaller installer;
    installer.run();
    return 0;
}
